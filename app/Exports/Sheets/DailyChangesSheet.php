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
            'Annotation',
        ];
    }

    /**
     * Only the stored columns: performance figures are recalculated from transactions when a
     * backup is imported, and calculating them for every day made large exports very slow.
     */
    public function query(): Builder
    {
        return DailyChange::myDailyChanges()
            ->when($this->empty, fn ($query) => $query->whereRaw('1 = 0'))
            ->select(['daily_change.date', 'daily_change.portfolio_id', 'daily_change.total_market_value', 'daily_change.annotation'])
            ->orderBy('daily_change.date')
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
            $daily_change->annotation,
        ];
    }

    public function title(): string
    {
        return 'Daily Changes';
    }
}
