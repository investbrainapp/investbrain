<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Imports\ValidatesPortfolioAccess;
use App\Models\BackupImport;
use App\Models\DailyChange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;

class DailyChangesSheet implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow, WithValidation
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
                    'message' => __('Importing daily changes...'),
                ]);
                DB::beginTransaction();
            },
        ];
    }

    // todo: only update annotation
    public function collection(Collection $dailyChanges)
    {
        $dailyChanges->chunk($this->batchSize())->each(function ($chunk) {

            $this->validatePortfolioAccess($chunk);

            // have to cast to native values
            $chunk = $chunk->map(function ($dailyChange) {

                return [
                    'total_market_value' => $dailyChange['total_market_value'],
                    'total_cost_basis' => $dailyChange['total_cost_basis'],
                    'total_gain' => $dailyChange['total_gain'],
                    'total_dividends_earned' => $dailyChange['total_dividends_earned'],
                    'realized_gains' => $dailyChange['realized_gains'],
                    'annotation' => $dailyChange['annotation'],
                    'portfolio_id' => $dailyChange['portfolio_id'],
                    'date' => Carbon::parse($dailyChange['date'])->toDateString(),
                ];
            });

            DailyChange::upsert(
                $chunk->toArray(),
                ['portfolio_id', 'date'],
                [
                    'total_market_value',
                    'total_cost_basis',
                    'total_gain',
                    'total_dividends_earned',
                    'realized_gains',
                    'annotation',
                    'portfolio_id',
                    'date',
                ]
            );
        });
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => ['required', 'uuid'],
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
