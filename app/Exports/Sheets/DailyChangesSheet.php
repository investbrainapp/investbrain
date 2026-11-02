<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\DailyChange;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class DailyChangesSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
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
     * Read in chunks, which needs a stable order (several portfolios share each date).
     */
    public function query(): Builder
    {
        return DailyChange::myDailyChanges()
            ->when($this->empty, fn ($query) => $query->whereRaw('1 = 0'))
            ->withDailyPerformance()
            ->addSelect('daily_change.annotation')
            ->groupBy('daily_change.annotation')
            ->orderBy('daily_change.portfolio_id');
    }

    /**
     * @param  DailyChange  $daily_change
     */
    public function map($daily_change): array
    {
        return [
            date_format($daily_change->date, 'Y-m-d'),
            $daily_change->portfolio_id,
            $daily_change->total_market_value,
            $daily_change->total_cost_basis,
            $daily_change->realized_gain_dollars,
            $daily_change->total_dividends_earned,
            $daily_change->annotation,
        ];
    }

    public function title(): string
    {
        return 'Daily Changes';
    }
}
