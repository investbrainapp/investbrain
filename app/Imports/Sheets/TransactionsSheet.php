<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Imports\ValidatesPortfolioAccess;
use App\Models\BackupImport;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Holding;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;

class TransactionsSheet implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use ValidatesPortfolioAccess;

    public function __construct(
        public BackupImport $backupImport
    ) {}

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                DB::commit();
                $this->backupImport->update([
                    'message' => __('Importing transactions...'),
                ]);
                DB::beginTransaction();
            },
        ];
    }

    public function collection(Collection $transactions)
    {

        // if has any transactions not in base currency, need to sync timeseries conversion rates
        if ($transactions->where('currency', '!=', config('investbrain.base_currency'))->isNotEmpty()) {

            $first_transaction_date = $transactions->min('date');
            CurrencyRate::timeSeriesRates('', $first_transaction_date);
        }

        // chunk transactions
        $transactions->chunk($this->batchSize())->each(function ($chunk) use ($transactions) {

            $this->validatePortfolioAccess($chunk);

            // have to cast to native values
            $chunk = $chunk->map(function ($transaction) {

                $date = Carbon::parse($transaction['date'])->toDateString();

                // if transaction not in base currency, need to convert
                if (
                    $transaction->currency == config('investbrain.base_currency')
                    || empty($transaction->currency)
                ) {
                    $cost_basis_base = $transaction['cost_basis'] ?? 0;
                    $sale_price_base = $transaction['sale_price'];
                } else {
                    $cost_basis_base = Currency::convert($transaction['cost_basis'], $transaction->currency, date: $date);
                    $sale_price_base = Currency::convert($transaction['sale_price'], $transaction->currency, date: $date);
                }

                return [
                    'id' => $transaction['transaction_id'] ?? Str::uuid()->toString(),
                    'symbol' => strtoupper($transaction['symbol']),
                    'portfolio_id' => $transaction['portfolio_id'],
                    'transaction_type' => $transaction['transaction_type'],
                    'quantity' => $transaction['quantity'],
                    'cost_basis' => $transaction['cost_basis'] ?? 0,
                    'sale_price' => $transaction['sale_price'],
                    'cost_basis_base' => $cost_basis_base,
                    'sale_price_base' => $sale_price_base,
                    'split' => boolval($transaction['split']) ? 1 : 0,
                    'reinvested_dividend' => boolval($transaction['reinvested_dividend']) ? 1 : 0,
                    'date' => $date,
                ];
            });

            Transaction::upsert(
                $chunk->toArray(),
                ['id'],
                [
                    'id',
                    'symbol',
                    'portfolio_id',
                    'transaction_type',
                    'quantity',
                    'cost_basis',
                    'sale_price',
                    'split',
                    'reinvested_dividend',
                    'date',
                ]
            );

            // get unique symbol/portfolio id combination and stub out related holdings
            $chunk->unique(fn ($item) => $item['symbol'].$item['portfolio_id'])
                ->each(function ($holding) use ($transactions) {

                    Holding::firstOrCreate([
                        'symbol' => $holding['symbol'],
                        'portfolio_id' => $holding['portfolio_id'],
                    ], [
                        'quantity' => 0,
                        'average_cost_basis' => 0,
                        'splits_synced_at' => now(),
                        'reinvest_dividends' => $transactions
                            ->where('symbol', $holding['symbol'])
                            ->where('portfolio', $holding['portfolio_id'])
                            ->where('reinvested_dividend', 1)->isNotEmpty() ? true : false, // todo: re-invested dividends is set on holdings
                    ]);
                });
        });
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['sometimes', 'nullable', 'uuid'],
            'symbol' => ['required', 'string'],
            'portfolio_id' => ['required', 'uuid'],
            'quantity' => ['required', 'min:0', 'numeric'],
            'transaction_type' => ['required', 'in:BUY,SELL'],
            'date' => ['required', 'date'],
            'quantity' => ['required', 'min:0', 'numeric'],
            'split' => ['sometimes', 'nullable', 'boolean'],
            'reinvested_dividend' => ['sometimes', 'nullable', 'boolean'],
            'cost_basis' => ['sometimes', 'nullable', 'min:0', 'numeric'],
            'sale_price' => ['sometimes', 'nullable', 'min:0', 'numeric'],
        ];
    }
}
