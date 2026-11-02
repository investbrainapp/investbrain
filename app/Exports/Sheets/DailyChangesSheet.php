<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\DailyChange;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DailyChangesSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        public bool $empty = false
    ) {}

    public function headings(): array
    {
        return [
            'Date',
            'Portfolio ID',
            'Total Market Value',
            'Total Cost Basis',
            'Realized Gains',
            'Total Dividends Earned',
            'Annotation',
        ];
    }

    /**
     * Read in a single query rather than in chunks: the performance columns are aggregated
     * over all of a portfolio's history, so every chunk would recalculate the whole result.
     */
    public function collection(): Collection
    {
        if ($this->empty) {
            return collect();
        }

        return DailyChange::myDailyChanges()
            ->withDailyPerformance()
            ->addSelect('daily_change.annotation')
            ->groupBy('daily_change.annotation')
            ->orderBy('daily_change.portfolio_id')
            ->get()
            ->map(fn ($daily_change) => [
                date_format($daily_change->date, 'Y-m-d'),
                $daily_change->portfolio_id,
                $daily_change->total_market_value,
                $daily_change->total_cost_basis,
                $daily_change->realized_gain_dollars,
                $daily_change->total_dividends_earned,
                $daily_change->annotation,
            ]);
    }

    public function title(): string
    {
        return 'Daily Changes';
    }
}
