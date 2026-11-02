<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\BackupExport;
use App\Models\DailyChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class DailyChangesSheet implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    /**
     * Performance figures loaded so far, keyed by portfolio ID then date.
     *
     * @var array<string, array<string, array{total_cost_basis: float|null, realized_gains: float|null, total_dividends_earned: float|null}>>
     */
    protected array $performance = [];

    public function __construct(
        public bool $empty = false,
        public ?BackupExport $backupExport = null
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
     * Only the stored columns. The performance figures are too slow to calculate here, so they
     * are calculated ahead of time per portfolio (see CalculateDailyPerformanceJob).
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
        $date = date_format($daily_change->date, 'Y-m-d');
        $performance = $this->performance($daily_change->portfolio_id)[$date] ?? [];

        return [
            $date,
            $daily_change->portfolio_id,
            $daily_change->total_market_value,
            $performance['total_cost_basis'] ?? null,
            $performance['realized_gains'] ?? null,
            $performance['total_dividends_earned'] ?? null,
            $daily_change->annotation,
        ];
    }

    /**
     * @return array<string, array{total_cost_basis: float|null, realized_gains: float|null, total_dividends_earned: float|null}>
     */
    protected function performance(string $portfolioId): array
    {
        if (is_null($this->backupExport)) {
            return [];
        }

        return $this->performance[$portfolioId] ??= json_decode(
            Storage::disk()->get($this->backupExport->performancePath($portfolioId)) ?? '[]',
            true
        ) ?? [];
    }

    public function title(): string
    {
        return 'Daily Changes';
    }
}
