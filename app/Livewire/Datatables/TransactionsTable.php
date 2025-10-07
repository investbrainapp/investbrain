<?php

declare(strict_types=1);

namespace App\Livewire\Datatables;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class TransactionsTable extends DataTableComponent
{
    public array $hiddenColumns = [];

    public function mount(): void
    {
        //
    }

    public function builder(): Builder
    {
        return Transaction::query()
            ->with(['portfolio', 'market_data'])
            ->myTransactions()
            ->addSelect(['portfolio_id', 'transaction_type', 'split', 'cost_basis'])
            ->selectRaw('
                (CASE
                    WHEN transaction_type = \'SELL\' 
                    THEN COALESCE(transactions.sale_price, 0)
                    ELSE COALESCE(market_data.market_value, 0)
                END) - COALESCE(transactions.cost_basis, 0) AS gain_dollars');
    }

    public function configure(): void
    {
        $this->hiddenColumns = ['name', 'cost_basis', 'gain_dollars'];

        $this->setTableWrapperAttributes([
            'default' => false,
            'default-styling' => false,
            'default-colors' => false,
            'class' => 'overflow-scroll',
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
        $this->setThAttributes(function (Column $column) {

            $attributes = [
                'default' => false,
                'default-styling' => false,
                'default-colors' => false,
                'class' => 'text-xs font-medium whitespace-nowrap uppercase tracking-wider text-nowrap',
            ];

            if (in_array($column->getField(), $this->hiddenColumns)) {
                $attributes['class'] = $attributes['class'].' hidden md:table-cell';
            }

            return $attributes;
        });
        $this->setThSortButtonAttributes(fn () => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'cursor-pointer',
        ]);
        $this->setTbodyAttributes([
            'default' => false,
            'default-styling' => true,
            'default-colors' => false,
        ]);
        $this->setTrAttributes(fn () => [
            'default' => false,
            'default-styling' => true,
            'default-colors' => false,
            'class' => 'cursor-pointer hover:bg-neutral/25',
        ]);
        $this->setTdAttributes(function (Column $column) {

            $attributes = [
                'default' => false,
                'default-styling' => false,
                'default-colors' => false,
                'class' => 'text-nowrap',
            ];

            if (in_array($column->getField(), $this->hiddenColumns)) {
                $attributes['class'] = $attributes['class'].' hidden md:table-cell';
            }

            return $attributes;
        });

        $this->setDefaultSort('date', 'desc');

        $this->setPerPageAccepted([10, 15, 20]);
        $this->setPerPage(15);
        $this->setSearchDisabled();
        $this->setColumnSelectDisabled();
        $this->setPerPageVisibilityDisabled();
        $this->setFooterDisabled();

        $this->setPrimaryKey('id');

        $this->setTableRowUrl(function ($row) {
            return route('holding.show', ['portfolio' => $row->portfolio_id, 'symbol' => $row->symbol]);

        })->setTableRowUrlTarget(function ($row) {

            return 'navigate';
        });
    }

    public function columns(): array
    {
        return [

            Column::make(__('Date'), 'date')
                ->sortable()
                ->format(fn ($value) => \Carbon\Carbon::parse($value)->format('M d, Y')),
            Column::make(__('Portfolio'), 'portfolio.title')
                ->sortable(),
            Column::make(__('Symbol'), 'symbol')
                ->sortable(),
            Column::make(__('Name'), 'market_data.name')
                ->sortable(),
            Column::make(__('Type'), 'transaction_type')
                ->label(fn ($row) => view('components.ui.badge', [
                    'value' => $row->split ? 'SPLIT'
                        : ($row->reinvested_dividend
                            ? 'REINVEST'
                            : $row->transaction_type),
                    'class' => ($row->transaction_type == 'BUY'
                        ? 'badge-success'
                        : 'badge-error').' badge-sm mr-3',
                ]))
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('transaction_type', $direction)),
            Column::make(__('Quantity'), 'quantity')
                ->sortable(),
            Column::make(__('Cost Basis'), 'cost_basis')
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('cost_basis', $direction))
                ->label(fn ($row) => Number::currency($row->cost_basis ?? 0, $row->market_data->currency)),
            Column::make(__('Gain/Loss'), 'gain_dollars')
                ->sortable(fn (Builder $query, string $direction) => $query->orderBy('gain_dollars', $direction))
                ->label(fn ($row) => Number::currency($row->gain_dollars ?? 0, $row->market_data->currency)),
        ];
    }
}
