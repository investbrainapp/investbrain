<?php

namespace App\Imports\Sheets;

use App\Models\BackupImport;
use App\Models\Portfolio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;

class PortfoliosSheet implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    public function __construct(
        public BackupImport $backupImport
    ) {}

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                DB::commit();
                $this->backupImport->update([
                    'message' => __('Importing portfolios...'),
                ]);
                DB::beginTransaction();
            },
        ];
    }

    public function collection(Collection $portfolios)
    {
        foreach ($portfolios as $index => $portfolio) {

            Portfolio::unguard(); // ensures we can set an owner for the portfolio

            $portfolio = Portfolio::fullAccess($this->backupImport->user_id)->updateOrCreate([
                'id' => $portfolio['portfolio_id'],
            ], [
                'id' => $portfolio['portfolio_id'] ?? null,
                'title' => $portfolio['title'],
                'wishlist' => $portfolio['wishlist'] ?? false,
                'notes' => $portfolio['notes'],
                'owner_id' => $this->backupImport->user_id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => ['sometimes', 'nullable', 'uuid'],
            'title' => ['required', 'string'],
            'wishlist' => ['sometimes', 'nullable', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
