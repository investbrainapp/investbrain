<?php

namespace App\Imports\Sheets;

use App\Models\DailyChange;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Imports\ValidatesPortfolioPermissions;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class DailyChangesSheet implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows, WithChunkReading
{
    use ValidatesPortfolioPermissions;

    public function collection(Collection $dailyChanges)
    {
        $this->validatePortfolioPermissions($dailyChanges);

        $chunkSize = 180; 

        $dailyChanges->chunk($chunkSize)->each(function ($chunk) {
            
            DailyChange::upsert(
                $chunk->toArray(),
                [
                    'portfolio_id',
                    'date'
                ],
                [
                    'total_market_value',
                    'total_cost_basis',
                    'total_gain',
                    'total_dividends_earned',
                    'realized_gains',
                    'annotation'
                ]
            );
        });
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => ['required', 'exists:portfolios,id'],
            'date' => ['required', 'date'],
            'total_market_value' => ['sometimes', 'nullable', 'numeric'],
            'total_cost_basis' => ['sometimes', 'nullable', 'min:0', 'numeric'],
            'total_gain' => ['sometimes', 'nullable', 'numeric'],
            'total_dividends' => ['sometimes', 'nullable', 'min:0', 'numeric'],
            'realized_gains' => ['sometimes', 'nullable', 'numeric'],
            'annotation' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
