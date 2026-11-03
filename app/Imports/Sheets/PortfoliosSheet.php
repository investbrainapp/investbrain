<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Imports\ImportSheet;
use App\Models\BackupImport;
use App\Models\Portfolio;

class PortfoliosSheet extends ImportSheet
{
    public function title(): string
    {
        return 'Portfolios';
    }

    /**
     * @return array<string, array{label: string, required: bool}>
     */
    public static function columns(): array
    {
        return [
            'portfolio_id' => [
                'label' => 'Portfolio ID',
                'required' => false,
            ],
            'title' => [
                'label' => 'Title',
                'required' => true,
            ],
            'notes' => [
                'label' => 'Notes',
                'required' => false,
            ],
            'wishlist' => [
                'label' => 'Wishlist',
                'required' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalize(array $row): array
    {
        return [
            'portfolio_id' => $this->string($row['portfolio_id'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'notes' => $this->string($row['notes'] ?? null),
            'wishlist' => $this->boolean($row['wishlist'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function rowRules(array $row): array
    {
        return [
            'portfolio_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'wishlist' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function persist(BackupImport $backupImport): void
    {
        foreach ($this->rows as $portfolio) {

            Portfolio::unguard(); // ensures we can set an owner for the portfolio

            Portfolio::fullAccess($backupImport->user_id)->updateOrCreate([
                'id' => $portfolio['portfolio_id'],
            ], [
                'id' => $portfolio['portfolio_id'],
                'title' => $portfolio['title'],
                'wishlist' => $portfolio['wishlist'],
                'notes' => $portfolio['notes'],
                'owner_id' => $backupImport->user_id,
            ]);
        }

        Portfolio::reguard();
    }
}
