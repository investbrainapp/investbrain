<?php

namespace App\Imports\Sheets;

use App\Models\DailyChange;
use App\Models\BackupImport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithUpserts;
use App\Rules\PortfolioAccessValidationRule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class DailyChangesSheet implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithUpserts, SkipsEmptyRows, WithEvents
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
                    'message' => __('Importing daily changes...'),
                ]);
                DB::beginTransaction();
            }
        ];
    }
    
    public function model(array $dailyChange)
    {
        return new DailyChange([
            'total_market_value' => $dailyChange['total_market_value'],
            'total_cost_basis' => $dailyChange['total_cost_basis'],
            'total_gain' => $dailyChange['total_gain'],
            'total_dividends_earned' => $dailyChange['total_dividends_earned'],
            'realized_gains' => $dailyChange['realized_gains'],
            'annotation' => $dailyChange['annotation'],
            'portfolio_id' => $dailyChange['portfolio_id'],
            'date' => Carbon::parse($dailyChange['date'])->format('Y-m-d')
        ]);
    }
    
    public function batchSize(): int
    {
        return 150;
    }

    public function uniqueBy()
    {
        return ['portfolio_id', 'date'];
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => ['required', new PortfolioAccessValidationRule($this->backupImport->user_id)], 
            'date' => ['required', 'date'],
            'total_market_value' => ['sometimes', 'nullable', 'numeric'],
            'total_cost_basis' => ['sometimes', 'nullable', 'min:0', 'numeric'],
            'total_gain' => ['sometimes', 'nullable', 'numeric'],
            'total_dividends_earned' => ['sometimes', 'nullable', 'min:0', 'numeric'],
            'realized_gains' => ['sometimes', 'nullable', 'numeric'],
            'annotation' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
