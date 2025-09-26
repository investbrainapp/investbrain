<?php

namespace App\Livewire\Datatables;

use App\Models\Holding;
use Illuminate\Support\Number;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\DataTableComponent;

class HoldingsTable extends DataTableComponent
{
    public $portfolio; 
    public array $hiddenColumns = [];

    public function mount ($portfolio): void
    {
        //
    }

    public function builder(): Builder
    { 
        return Holding::query()
            ->portfolio($this->portfolio->id)
            ->with(['market_data'])
            ->withCount(['transactions as num_transactions' => function ($query) {
                return $query->whereRaw('transactions.symbol = holdings.symbol');
            }])
            ->withPerformance();
    }

    public function configure(): void
    {
        $this->hiddenColumns = ['name', 'average_cost_basis', 'market_value', 'fifty_two_week_low', 'fifty_two_week_high'];

        $this->setTableWrapperAttributes([
            'default' => false, 
            'default-styling' => false,
            'default-colors' => false,
            'class' => 'overflow-scroll'
        ]); 
        $this->setTableAttributes([
            'default' => false, 
            'default-styling' => false,
            'default-colors' => false,
            'class' => 'table',
        ]);
        $this->setTheadAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setThAttributes(function(Column $column) {

            $attributes = [
                'default' => false,
                'default-styling' => false,
                'default-colors' => false,
                'class' => 'text-xs font-medium whitespace-nowrap uppercase tracking-wider text-nowrap'
            ];

            if (in_array($column->getField(), $this->hiddenColumns)) {
                $attributes['class'] = $attributes['class'] . ' hidden md:table-cell';
            }

            return $attributes;
        });
        $this->setThSortButtonAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'cursor-pointer'
        ]);
        $this->setTbodyAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setTrAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'cursor-pointer hover:bg-neutral/25'
        ]);
        $this->setTdAttributes(function(Column $column) {
            
            $attributes = [
                'default' => false,
                'default-styling' => false,
                'default-colors' => false,
                'class' => 'text-nowrap'
            ];

            if (in_array($column->getField(), $this->hiddenColumns)) {
                $attributes['class'] = $attributes['class'] . ' hidden md:table-cell';
            }

            return $attributes;
        });

        $this->setDefaultSort('symbol', 'asc');

        $this->setToolsDisabled();
        $this->setFooterDisabled();
        $this->setPaginationDisabled();
        $this->setDisplayPaginationDetailsDisabled();

        $this->setPrimaryKey('id');

        $this->setTableRowUrl(function($row) {
            return route('holding.show', ['portfolio' => $row->portfolio_id, 'symbol' => $row->symbol]);
            
        })->setTableRowUrlTarget(function($row) {
            
            return 'navigate';
        });
    }

    public function columns(): array
    {
        return [
            Column::make(__('Symbol'), 'symbol')
                ->sortable(),
            Column::make(__('Name'), 'market_data.name')
                ->sortable(),
            Column::make(__('Quantity'), 'quantity')
                ->sortable(),
            Column::make(__('Average Cost Basis'), 'average_cost_basis')
                ->sortable()
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') ),
            Column::make(__('Total Cost Basis'), 'total_cost_basis')
                ->sortable()
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') ),
            Column::make(__('Market Value'), 'market_data.market_value')
                ->sortable()
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') ),
            Column::make(__('Total Market Value'))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('total_market_value', $direction))
                ->label(fn ($row) => Number::currency($row->total_market_value ?? 0, $row->market_data?->currency ?? '')),
            Column::make(__('Market Gain/Loss'))
                ->html()
                ->label(fn($row) => Number::currency($row->market_gain_dollars ?? 0, $row->market_data?->currency ?? '') . view('components.ui.gain-loss-arrow-badge', [
                    'costBasis' => $row->average_cost_basis,
                    'marketValue' => $row->market_data?->market_value,
                    'small' => true,
                ]))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('market_gain_dollars', $direction)),
            Column::make(__('Realized Gain/Loss'), 'realized_gain_dollars')
                ->sortable()
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') )
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') ),
            Column::make(__('Dividends Earned'), 'dividends_earned')
                ->sortable()
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') ),
            Column::make(__('52 week low'), 'market_data.fifty_two_week_low')
                ->sortable()
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') ),
            Column::make(__('52 week high'), 'market_data.fifty_two_week_high')
                ->sortable()
                ->format(fn($value, $row) => Number::currency($value ?? 0, $row->market_data?->currency ?? '') ),
            Column::make(__('Number of Transactions'))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('num_transactions', $direction))
                ->label(fn ($row) => $row->num_transactions),
            Column::make(__('Last Refreshed'), 'market_data.updated_at')
                ->sortable()
                ->format(fn($value) => \Carbon\Carbon::parse($value)->diffForHumans() )
        ];
    }
}
