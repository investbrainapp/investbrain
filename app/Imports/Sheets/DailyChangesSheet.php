<?php

declare(strict_types=1);

namespace App\Imports\Sheets;

use App\Imports\ImportSheet;
use App\Models\DailyChange;

class DailyChangesSheet extends ImportSheet
{
    public function title(): string
    {
        return 'Daily Changes';
    }

    /**
     * @return array<string, array{label: string, required: bool}>
     */
    public static function columns(): array
    {
        return [
            'date' => [
                'label' => 'Date',
                'required' => true,
            ],
            'portfolio_id' => [
                'label' => 'Portfolio ID',
                'required' => true,
            ],
            'annotation' => [
                'label' => 'Annotation',
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
            'date' => $this->date($row['date'] ?? null),
            'annotation' => $this->string($row['annotation'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function rowRules(array $row): array
    {
        return [
            'portfolio_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
            'annotation' => ['nullable', 'string'],
        ];
    }

    public function persist(): void
    {
        collect($this->rows)->chunk($this->batchSize())->each(function ($chunk) {

            DailyChange::upsert(
                $chunk->values()->toArray(),
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
}
