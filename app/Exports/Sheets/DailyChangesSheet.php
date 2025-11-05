<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\DailyChange;
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
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        if ($this->empty) {
            return collect();
        }

        return DailyChange::myDailyChanges()
            ->withDailyPerformance()
            ->get()
            ->map(function ($daily_change) {
                return [
                    'date' => date_format($daily_change->date, 'Y-m-d'),
                    'portfolio_id' => $daily_change->portfolio_id,
                    'total_market_value' => $daily_change->total_market_value,
                    'total_cost_basis' => $daily_change->total_cost_basis,
                    'realized_gains' => $daily_change->realized_gain_dollars,
                    'total_dividends_earned' => $daily_change->total_dividends_earned,
                    'annotation' => $daily_change->annotation,
                ];
            });
    }

    public function title(): string
    {
        return 'Daily Changes';
    }
}
