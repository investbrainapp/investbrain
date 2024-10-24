<?php

namespace App\Imports\Sheets;

use App\Models\Portfolio;
use App\Models\BackupImport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\SyncDailyChange;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PortfoliosSheet implements ToCollection, WithValidation, WithHeadingRow, SkipsEmptyRows, WithEvents
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
                    'message' => __('Importing portfolios...'),
                ]);
                DB::beginTransaction();
            }
        ];
    }

    public function collection(Collection $portfolios)
    {
        foreach ($portfolios as $index => $portfolio) {

            Portfolio::unguard();

            $portfolio = Portfolio::fullAccess($this->backupImport->user_id)->updateOrCreate([
                'id' => $portfolio['portfolio_id']
            ], [
                'id' => $portfolio['portfolio_id'] ?? null,
                'title' => $portfolio['title'],
                'wishlist' => $portfolio['wishlist'] ?? false,
                'notes' => $portfolio['notes'],
            ]);

            Artisan::queue(SyncDailyChange::class, ['portfolio_id' => $portfolio->id])->delay(30 + ($index * 10));
        }
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => ['sometimes', 'nullable'],
            'title' => ['required', 'string'],
            'wishlist' => ['sometimes', 'nullable', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
