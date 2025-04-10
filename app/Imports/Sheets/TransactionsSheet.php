<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Imports\ValidatesPortfolioAccess;
use App\Models\BackupImport;
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

        $transactions->chunk($this->batchSize())->each(function ($chunk) {

            $this->validatePortfolioAccess($chunk);

            // have to cast to native values
            $chunk = $chunk->map(function ($transaction) {

                return [
                    'id' => $transaction['transaction_id'] ?? Str::uuid()->toString(),
                    'symbol' => strtoupper($transaction['symbol']),
                    'portfolio_id' => $transaction['portfolio_id'],
                    'transaction_type' => $transaction['transaction_type'],
                    'quantity' => $transaction['quantity'],
                    'cost_basis' => $transaction['cost_basis'] ?? 0,
                    'sale_price' => $transaction['sale_price'],
                    'split' => boolval($transaction['split']) ? 1 : 0,
                    'reinvested_dividend' => boolval($transaction['reinvested_dividend']) ? 1 : 0,
                    'date' => Carbon::parse($transaction['date'])->toDateString(),
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

            // stub out related holdings
            $chunk->unique(fn ($item) => $item['symbol'].$item['portfolio_id'])
                ->each(function ($holding) {

                    Holding::firstOrCreate([
                        'symbol' => $holding['symbol'],
                        'portfolio_id' => $holding['portfolio_id'],
                    ], [
                        'quantity' => 0,
                        'average_cost_basis' => 0,
                        'splits_synced_at' => now(),
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
