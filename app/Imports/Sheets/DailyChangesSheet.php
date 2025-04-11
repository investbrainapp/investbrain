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
                    'message' => __('Preparing to import daily changes...'),
                ]);
                DB::beginTransaction();
            },
        ];
    }

    public function collection(Collection $dailyChanges)
    {
        $totalBatches = count($dailyChanges) / $this->batchSize();

        $dailyChanges->chunk($this->batchSize())->each(function ($chunk, $index) use ($totalBatches) {

            $this->validatePortfolioAccess($chunk);

            $this->backupImport->update([
                'message' => __('Importing daily changes (Batch :currentBatch of :totalBatches)...', ['currentBatch' => $index + 1, 'totalBatches' => $totalBatches]),
            ]);

            // have to cast to native values
            $chunk = $chunk->map(function ($dailyChange) {

                return [
                    'annotation' => $dailyChange['annotation'],
                    'portfolio_id' => $dailyChange['portfolio_id'],
                    'date' => Carbon::parse($dailyChange['date'])->toDateString(),
                ];
            });

            DailyChange::upsert(
                $chunk->toArray(),
                ['portfolio_id', 'date'],
                [
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
            'annotation' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
