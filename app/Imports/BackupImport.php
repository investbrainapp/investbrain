<?php

declare(strict_types=1);

namespace App\Imports;

use App\Imports\Sheets\ConfigSheet;
use App\Imports\Sheets\DailyChangesSheet;
use App\Imports\Sheets\PortfoliosSheet;
use App\Imports\Sheets\TransactionsSheet;
use App\Jobs\SyncImportedHoldingsJob;
use App\Models\BackupImport as BackupImportModel;
use App\Models\Holding;
use App\Models\MarketData;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Throwable;

/**
 * Reads every sheet of an Investbrain workbook, validates the whole file (so all of
 * its problems can be reported at once), then writes it in a single transaction.
 */
class BackupImport implements Import, SkipsUnknownSheets, WithMultipleSheets
{
    use Importable;

    public PortfoliosSheet $portfoliosSheet;

    public TransactionsSheet $transactionsSheet;

    public DailyChangesSheet $dailyChangesSheet;

    public ConfigSheet $configSheet;

    /**
     * @var list<array{sheet: string, row: int|null, column: string|null, value: string|null, message: string}>
     */
    protected array $errors = [];

    /**
     * Rows that already failed validation, keyed by sheet title then row number (0 = whole sheet).
     *
     * @var array<string, array<int, true>>
     */
    protected array $invalidRows = [];

    /**
     * Existing transactions this import overwrites, so their previous holdings get re-synced.
     *
     * @var Collection<string, Transaction>
     */
    protected Collection $replacedTransactions;

    public function __construct(
        public BackupImportModel $backupImportModel
    ) {
        $this->portfoliosSheet = new PortfoliosSheet;
        $this->transactionsSheet = new TransactionsSheet;
        $this->dailyChangesSheet = new DailyChangesSheet;
        $this->configSheet = new ConfigSheet;
        $this->replacedTransactions = collect();
    }

    public function sheets(): array
    {
        return [
            'Portfolios' => $this->portfoliosSheet,
            'Transactions' => $this->transactionsSheet,
            'Daily Changes' => $this->dailyChangesSheet,
            'Config' => $this->configSheet,
        ];
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        // every sheet is optional
    }

    /**
     * Validate the rows that were read and, when the file is clean, save them.
     *
     * @return array{portfolios: int, transactions: int}
     *
     * @throws ImportValidationException
     */
    public function process(): array
    {
        $this->validate();

        $this->backupImportModel->reportProgress(__('Saving your portfolios and transactions...'), 80);

        $holdings = DB::transaction(function () {

            $this->portfoliosSheet->persist($this->backupImportModel);
            $this->transactionsSheet->persist();
            $holdings = $this->syncHoldings();
            $this->dailyChangesSheet->persist();
            $this->configSheet->persist($this->backupImportModel);

            return $holdings;
        });

        $holdings->pluck('portfolio_id')->unique()->each(fn ($portfolioId) => cache()->forget('portfolio-metrics-'.$portfolioId));

        // network heavy follow up work (dividends, daily history) shouldn't hold up or break the import
        if ($holdings->isNotEmpty()) {

            SyncImportedHoldingsJob::dispatch(
                $holdings->pluck('portfolio_id')->unique()->values()->all(),
                $holdings->pluck('symbol')->unique()->values()->all(),
            );
        }

        return [
            'portfolios' => count($this->portfoliosSheet->rows),
            'transactions' => count($this->transactionsSheet->rows),
        ];
    }

    /**
     * @throws ImportValidationException
     */
    protected function validate(): void
    {
        if (collect($this->sheets())->every(fn ($sheet) => empty($sheet->rows))) {

            throw new ImportValidationException([
                $this->portfoliosSheet->error(null, null, __('No data found. Check the sheet names and headings.')),
            ]);
        }

        $this->backupImportModel->reportProgress(__('Checking your file for problems...'), 20);

        foreach ($this->sheets() as $sheet) {
            $this->addErrors($sheet->validationErrors());
        }

        $this->resolvePortfolios();
        $this->checkTransactionIds();
        $this->checkSymbols();
        $this->checkSellQuantities();

        if (! empty($this->errors)) {

            $sheetOrder = array_keys($this->sheets());

            throw new ImportValidationException(
                collect($this->errors)
                    ->sortBy([
                        fn ($a, $b) => array_search($a['sheet'], $sheetOrder) <=> array_search($b['sheet'], $sheetOrder),
                        fn ($a, $b) => ($a['row'] ?? 0) <=> ($b['row'] ?? 0),
                    ])
                    ->values()
                    ->all()
            );
        }
    }

    /**
     * Give new portfolios an ID and point every transaction and daily change at a
     * portfolio the user can write to.
     */
    protected function resolvePortfolios(): void
    {
        $userId = $this->backupImportModel->user_id;

        $accessible = Portfolio::fullAccess($userId)->get(['id', 'title']);

        $references = collect([
            ...array_column($this->portfoliosSheet->rows, 'portfolio_id'),
            ...array_column($this->transactionsSheet->rows, 'portfolio_id'),
            ...array_column($this->dailyChangesSheet->rows, 'portfolio_id'),
        ])->filter(fn ($reference) => is_string($reference) && Str::isUuid($reference))
            ->map(fn ($reference) => strtolower($reference))
            ->unique();

        $existing = $references->chunk(1000)
            ->flatMap(fn ($ids) => Portfolio::whereIn('id', $ids)->pluck('id'))
            ->flip();

        // portfolios in the file
        $filePortfolios = collect();

        foreach ($this->portfoliosSheet->rows as $rowNumber => $row) {

            if ($this->isInvalid($this->portfoliosSheet, $rowNumber)) {
                continue;
            }

            $id = is_null($row['portfolio_id']) ? Str::uuid()->toString() : strtolower($row['portfolio_id']);

            if ($filePortfolios->has($id)) {
                $this->addError($this->portfoliosSheet->error($rowNumber, 'portfolio_id', __('This Portfolio ID is used more than once in the Portfolios sheet.'), $id));

                continue;
            }

            if ($existing->has($id) && ! $accessible->contains('id', $id)) {
                $this->addError($this->portfoliosSheet->error($rowNumber, 'portfolio_id', __('You do not have access to that portfolio.'), $id));

                continue;
            }

            $this->portfoliosSheet->rows[$rowNumber]['portfolio_id'] = $id;
            $filePortfolios->put($id, $row['title']);
        }

        $portfolioIds = $filePortfolios->keys()->merge($accessible->pluck('id'))->flip();

        foreach ([$this->transactionsSheet, $this->dailyChangesSheet] as $sheet) {

            foreach ($sheet->rows as $rowNumber => $row) {

                if ($this->isInvalid($sheet, $rowNumber)) {
                    continue;
                }

                $id = strtolower($row['portfolio_id']);

                $error = match (true) {
                    $portfolioIds->has($id) => null,
                    $existing->has($id) => __('You do not have access to that portfolio.'),
                    default => __('No portfolio with this ID was found in the Portfolios sheet or in your account.'),
                };

                if ($error) {
                    $this->addError($sheet->error($rowNumber, 'portfolio_id', $error, $row['portfolio_id']));

                    continue;
                }

                $sheet->rows[$rowNumber]['portfolio_id'] = $id;
            }
        }
    }

    /**
     * Updating an existing transaction is only allowed when the user has full access to
     * the portfolio it currently belongs to.
     */
    protected function checkTransactionIds(): void
    {
        $seen = [];

        foreach ($this->transactionsSheet->rows as $rowNumber => $row) {

            if (is_null($row['transaction_id']) || $this->isInvalid($this->transactionsSheet, $rowNumber)) {
                continue;
            }

            $id = strtolower($row['transaction_id']);

            if (isset($seen[$id])) {
                $this->addError($this->transactionsSheet->error($rowNumber, 'transaction_id', __('This Transaction ID is used more than once in the Transactions sheet.'), $id));
            }

            $seen[$id] = true;
            $this->transactionsSheet->rows[$rowNumber]['transaction_id'] = $id;
        }

        $this->replacedTransactions = collect(array_keys($seen))->chunk(1000)
            ->flatMap(fn ($ids) => Transaction::whereIn('id', $ids)->get(['id', 'portfolio_id', 'symbol']))
            ->keyBy('id');

        $accessible = Portfolio::fullAccess($this->backupImportModel->user_id)
            ->whereIn('id', $this->replacedTransactions->pluck('portfolio_id')->unique())
            ->pluck('id')
            ->flip();

        foreach ($this->transactionsSheet->rows as $rowNumber => $row) {

            $existing = $this->replacedTransactions->get($row['transaction_id'] ?? '');

            if ($existing && ! $accessible->has($existing->portfolio_id)) {
                $this->addError($this->transactionsSheet->error($rowNumber, 'transaction_id', __('You do not have access to that portfolio.'), $row['transaction_id']));
            }
        }

        // new transactions need an ID up front so we can find them again after the upsert
        foreach ($this->transactionsSheet->rows as $rowNumber => $row) {
            $this->transactionsSheet->rows[$rowNumber]['transaction_id'] ??= Str::uuid()->toString();
        }
    }

    /**
     * Make sure market data exists for every symbol so holdings can be valued (and the
     * transaction currency defaulted) once the import completes.
     */
    protected function checkSymbols(): void
    {
        $rows = $this->validRows($this->transactionsSheet);

        $symbols = $rows->pluck('symbol')->unique()->values();

        $marketData = $symbols->chunk(1000)
            ->flatMap(fn ($chunk) => MarketData::whereIn('symbol', $chunk)->get())
            ->keyBy('symbol');

        $missing = $symbols->diff($marketData->keys())->values();

        foreach ($missing as $index => $symbol) {

            $this->backupImportModel->reportProgress(
                __('Looking up :symbol (:current of :total)...', ['symbol' => $symbol, 'current' => $index + 1, 'total' => $missing->count()]),
                30 + (int) (45 * $index / $missing->count())
            );

            try {
                $marketData->put($symbol, MarketData::getMarketData($symbol));
            } catch (Throwable) {
                // reported against each row below
            }
        }

        foreach ($rows as $rowNumber => $row) {

            if (! $marketData->has($row['symbol'])) {
                $this->addError($this->transactionsSheet->error($rowNumber, 'symbol', __('Could not find symbol.'), $row['symbol']));

                continue;
            }

            $this->transactionsSheet->rows[$rowNumber]['currency'] ??= $marketData->get($row['symbol'])->currency ?? config('investbrain.base_currency');
        }
    }

    /**
     * Like the transaction form, don't allow selling more shares than were held on that day.
     */
    protected function checkSellQuantities(): void
    {
        $rows = $this->validRows($this->transactionsSheet);

        if ($rows->where('transaction_type', 'SELL')->isEmpty()) {
            return;
        }

        $pairKey = fn ($transaction) => $transaction['portfolio_id'].'|'.$transaction['symbol'];

        $pairs = $rows->map($pairKey)->unique()->flip();

        $existing = Transaction::query()
            ->whereIn('portfolio_id', $rows->pluck('portfolio_id')->unique())
            ->whereIn('symbol', $rows->pluck('symbol')->unique())
            ->get(['id', 'portfolio_id', 'symbol', 'transaction_type', 'quantity', 'date'])
            ->reject(fn ($transaction) => $this->replacedTransactions->has($transaction->id))
            ->map(fn ($transaction) => [
                'row' => null,
                'portfolio_id' => $transaction->portfolio_id,
                'symbol' => $transaction->symbol,
                'transaction_type' => $transaction->transaction_type,
                'quantity' => (float) $transaction->quantity,
                'date' => $transaction->date->toDateString(),
            ])
            ->filter(fn ($transaction) => $pairs->has($pairKey($transaction)));

        $imported = $rows->map(fn ($row, $rowNumber) => [
            'row' => $rowNumber,
            'portfolio_id' => $row['portfolio_id'],
            'symbol' => $row['symbol'],
            'transaction_type' => $row['transaction_type'],
            'quantity' => (float) $row['quantity'],
            'date' => $row['date'],
        ]);

        $existing->concat($imported->values())
            ->groupBy($pairKey)
            ->each(function (Collection $transactions) {

                $owned = 0;

                // same day purchases count towards what can be sold that day
                $transactions->sortBy([['date', 'asc'], ['transaction_type', 'asc']])
                    ->each(function ($transaction) use (&$owned) {

                        if ($transaction['transaction_type'] == 'BUY') {
                            $owned += $transaction['quantity'];

                            return;
                        }

                        if (round($transaction['quantity'], 4) > round($owned, 4)) {

                            if (! is_null($transaction['row'])) {
                                $this->addError($this->transactionsSheet->error($transaction['row'], 'quantity', __('You cannot sell :quantity :symbol on :date because only :owned are held in that portfolio on that date.', [
                                    'quantity' => $transaction['quantity'],
                                    'symbol' => $transaction['symbol'],
                                    'date' => $transaction['date'],
                                    'owned' => round(max($owned, 0), 4),
                                ]), $transaction['quantity']));
                            }

                            return;
                        }

                        $owned -= $transaction['quantity'];
                    });
            });
    }

    /**
     * Sync every holding touched by the import (including holdings a transaction moved away from).
     *
     * @return Collection<int, array{portfolio_id: string, symbol: string}>
     */
    protected function syncHoldings(): Collection
    {
        $holdings = collect($this->transactionsSheet->rows)
            ->map(fn ($transaction) => ['portfolio_id' => $transaction['portfolio_id'], 'symbol' => $transaction['symbol']])
            ->concat($this->replacedTransactions->map(fn ($transaction) => ['portfolio_id' => $transaction->portfolio_id, 'symbol' => $transaction->symbol])->values())
            ->unique(fn ($holding) => $holding['portfolio_id'].$holding['symbol'])
            ->values();

        $holdings->each(function ($holding) {

            Holding::firstOrNew($holding, [
                'quantity' => 0,
                'average_cost_basis' => 0,
                'splits_synced_at' => now(),
            ])->syncTransactionsAndDividends();
        });

        return $holdings;
    }

    /**
     * @param  list<array{sheet: string, row: int|null, column: string|null, value: string|null, message: string}>  $errors
     */
    protected function addErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $this->addError($error);
        }
    }

    /**
     * @param  array{sheet: string, row: int|null, column: string|null, value: string|null, message: string}  $error
     */
    protected function addError(array $error): void
    {
        $this->errors[] = $error;
        $this->invalidRows[$error['sheet']][$error['row'] ?? 0] = true;
    }

    protected function isInvalid(ImportSheet $sheet, int $rowNumber): bool
    {
        return isset($this->invalidRows[$sheet->title()][$rowNumber])
            || isset($this->invalidRows[$sheet->title()][0]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function validRows(ImportSheet $sheet): Collection
    {
        return collect($sheet->rows)->reject(fn ($row, $rowNumber) => $this->isInvalid($sheet, $rowNumber));
    }
}
