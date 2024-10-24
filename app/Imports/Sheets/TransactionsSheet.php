<?php

namespace App\Imports\Sheets;

use App\Models\Holding;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\BackupImport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Concerns\WithUpserts;
use App\Rules\PortfolioAccessValidationRule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithEvents;

class TransactionsSheet implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithUpserts, SkipsEmptyRows, WithEvents
{

    public function __construct(
        public BackupImport $backupImport
    ) { }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                DB::commit();
                $this->backupImport->update([
                    'message' => __('Importing transactions...'),
                ]);
                DB::beginTransaction();
            }
        ];
    }

    public function model(array $transaction)
    {
        $transaction = new Transaction([
            'id' => $transaction['transaction_id'] ?? Str::uuid()->toString(),
            'symbol' => strtoupper($transaction['symbol']),
            'portfolio_id' => $transaction['portfolio_id'],
            'transaction_type' => $transaction['transaction_type'],
            'quantity' => $transaction['quantity'],
            'cost_basis' => $transaction['cost_basis'] ?? 0,
            'sale_price' => $transaction['sale_price'],
            'split' => boolval($transaction['split']) ? 1 : 0,
            'reinvested_dividend' => boolval($transaction['reinvested_dividend']) ? 1 : 0,
            'date' => Carbon::parse($transaction['date'])->format('Y-m-d')
        ]);

        // stub out related holding
        Holding::firstOrCreate([
            'symbol' => $transaction->symbol,
            'portfolio_id' => $transaction->portfolio_id
        ], [
            'quantity' => 0,
            'average_cost_basis' => 0,
        ]);

        return $transaction;
    }
    
    public function batchSize(): int
    {
        return 150;
    }

    public function uniqueBy()
    {
        return 'id';
    }

    public function rules(): array
    {
        return [
            'transaction_id' => ['sometimes', 'nullable'],
            'symbol' => ['required', 'string'],
            'portfolio_id' => ['required', new PortfolioAccessValidationRule($this->backupImport->user_id)],
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
