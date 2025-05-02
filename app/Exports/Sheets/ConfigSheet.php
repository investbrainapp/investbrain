<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Models\Holding;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConfigSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        public bool $empty = false
    ) {}

    public function headings(): array
    {
        return [
            'Key',
            'Value',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $configs = collect();

        if ($this->empty) {
            return $configs;
        }

        // collect user settings
        $configs->push([
            'key' => 'name',
            'value' => auth()->user()->name,
        ], [
            'key' => 'locale',
            'value' => auth()->user()->getLocale(),
        ], [
            'key' => 'display_currency',
            'value' => auth()->user()->getCurrency(),
        ]);

        // reinvested holdings
        $reinvested_holdings = Holding::myHoldings()->where('reinvest_dividends', true)->get(['portfolio_id', 'symbol']);
        if ($reinvested_holdings->isNotEmpty()) {
            $configs->push([
                'key' => 'reinvested_dividends',
                'value' => $reinvested_holdings->toJson(),
            ]);
        }

        return $configs;
    }

    public function title(): string
    {
        return 'Config';
    }
}
