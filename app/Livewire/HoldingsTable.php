<?php

namespace App\Livewire;

use App\Models\Holding;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\DataTableComponent;

class HoldingsTable extends DataTableComponent
{
    public $portfolio; 

    public function mount ($portfolio): void
    {
        //
    }

    public function goToHolding($holding)
    {
        return $this->redirect(route('holding.show', ['portfolio' => $holding['portfolio_id'], 'symbol' => $holding['symbol']]), navigate: true);
    }

    public function builder(): Builder
    {
        
        return Holding::query()
            ->portfolio($this->portfolio->id)
            ->withCount(['transactions as num_transactions' => function ($query) {
                return $query->whereRaw('transactions.symbol = holdings.symbol');
            }])
            ->withPerformance();
    }

    public function configure(): void
    {
        $this->setPaginationDisabled();
        $this->setSearchDisabled();
        $this->setColumnSelectDisabled();

        $this->setTableWrapperAttributes([
            'default' => false, 
            'default-styling' => false,
            'default-colors' => false,
        ]); 
        $this->setTableAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'table',
        ]);
        $this->setTableAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setTheadAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setThAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false
        ]);
        $this->setThSortButtonAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'text-secondary'
        ]);
        $this->setTbodyAttributes([
            'default' => false, 
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setTrAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false
        ]);
        $this->setTdAttributes(fn() => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false
        ]);

        $this->setFooterDisabled();
        $this->setDisplayPaginationDetailsDisabled();
        
        $this->setPrimaryKey('id');

    }

    public function columns(): array
    {
        return [
            Column::make(__('Symbol'), 'symbol')
                ->attributes(function ($row) {
                    return [
                        'class' => '!bg-red',
                        'default' => true,
                    ];
                })  
                ->sortable(),
            Column::make(__('Name'), 'market_data.name')
                ->sortable(),
            Column::make(__('Quantity'), 'quantity')
                ->sortable(),
            Column::make(__('Average Cost Basis'), 'average_cost_basis')
                ->sortable(),
            Column::make(__('Total Cost Basis'), 'total_cost_basis')
                ->sortable(),
            Column::make(__('Market Value'), 'market_data.market_value')
                ->sortable(),
            Column::make(__('Total Market Value'))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('total_market_value', $direction))
                ->label(fn ($row, Column $column) => $row->total_market_value),
            Column::make(__('Market Gain/Loss'))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('market_gain_dollars', $direction))
                ->label(fn ($row, Column $column) => $row->market_gain_dollars),
            Column::make(__('Market Gain/Loss'))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('market_gain_percent', $direction))
                ->label(fn ($row, Column $column) => $row->market_gain_percent),
            Column::make(__('Realized Gain/Loss'), 'realized_gain_dollars')
                ->sortable(),
            Column::make(__('Dividends Earned'), 'dividends_earned')
                ->sortable(),
            Column::make(__('52 week low'), 'market_data.fifty_two_week_low')
                ->sortable(),
            Column::make(__('52 week high'), 'market_data.fifty_two_week_high')
                ->sortable(),
            Column::make(__('Number of Transactions'))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('num_transactions', $direction))
                ->label(fn ($row, Column $column) => $row->num_transactions),
            Column::make(__('Last Refreshed'), 'market_data.updated_at')
                ->sortable()
                ->format(fn($value) => \Carbon\Carbon::parse($value)->diffForHumans() )
        ];
    }
}
