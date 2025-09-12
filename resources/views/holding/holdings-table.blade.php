<?php

use App\Models\Portfolio;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public Portfolio $portfolio;

    public array $sortBy = ['column' => 'symbol', 'direction' => 'asc'];

    public array $headers;

    public function mount()
    {
        $this->headers = [
            ['key' => 'symbol', 'label' => __('Symbol')],
            ['key' => 'market_data_name', 'label' => __('Name'), 'sortable' => true, 'class' => 'hidden md:table-cell'],
            ['key' => 'quantity', 'label' => __('Quantity')],
            ['key' => 'average_cost_basis', 'label' => __('Average Cost Basis')],
            ['key' => 'total_cost_basis', 'label' => __('Total Cost Basis'), 'class' => 'hidden md:table-cell'],
            ['key' => 'market_data_market_value', 'label' => __('Market Value')],
            ['key' => 'total_market_value', 'label' => __('Total Market Value'), 'class' => 'hidden md:table-cell'],
            ['key' => 'market_gain_dollars', 'label' => __('Market Gain/Loss')],
            ['key' => 'market_gain_percent', 'label' => __('Market Gain/Loss'), 'class' => 'hidden md:table-cell'],
            ['key' => 'realized_gain_dollars', 'label' => __('Realized Gain/Loss')],
            ['key' => 'dividends_earned', 'label' => __('Dividends Earned')],
            ['key' => 'market_data_fifty_two_week_low', 'label' => __('52 week low'), 'class' => 'hidden md:table-cell'],
            ['key' => 'market_data_fifty_two_week_high', 'label' => __('52 week high'), 'class' => 'hidden md:table-cell'],
            ['key' => 'num_transactions', 'label' => __('Number of Transactions')],
            ['key' => 'market_data_updated_at', 'label' => __('Last Refreshed')],
        ];
    }

    public function holdings(): Collection
    {

        $holdings = $this->portfolio
            ->holdings()
            ->withCount(['transactions as num_transactions' => function ($query) {
                return $query->whereRaw('transactions.symbol = holdings.symbol');
            }])
            ->orderBy(...array_values($this->sortBy))
                        // ->where('holdings.quantity', '>', 0)
            ->get();

        return $holdings;
    }

    public function goToHolding($holding)
    {
        return $this->redirect(route('holding.show', ['portfolio' => $holding['portfolio_id'], 'symbol' => $holding['symbol']]));
    }
}; ?>


<x-table 
    :headers="$headers" 
    :rows="$this->holdings()" 
    :sort-by="$sortBy"
    @row-click="$wire.goToHolding($event.detail)"
>
    @scope('cell_average_cost_basis', $row)
        {{ Number::currency($row->average_cost_basis ?? 0, $row->market_data->currency) }}
    
    @endscope
    @scope('cell_total_cost_basis', $row)
        {{ Number::currency($row->total_cost_basis ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_realized_gain_dollars', $row)
        {{ Number::currency($row->realized_gain_dollars ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_market_gain_dollars', $row)
        {{ Number::currency($row->market_gain_dollars ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_market_gain_percent', $row)
        <x-gain-loss-arrow-badge 
            :cost-basis="$row->average_cost_basis"
            :market-value="$row->market_data->market_value"  
        />
    @endscope
    @scope('cell_market_data_market_value', $row)
        {{ Number::currency($row->market_data_market_value ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_market_data_fifty_two_week_low', $row)
        {{ Number::currency($row->market_data_fifty_two_week_low ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_market_data_fifty_two_week_high', $row)
        {{ Number::currency($row->market_data_fifty_two_week_high ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_total_market_value', $row)
        {{ Number::currency($row->total_market_value ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_dividends_earned', $row)
        {{ Number::currency($row->dividends_earned ?? 0, $row->market_data->currency) }}
    @endscope
    @scope('cell_market_data_updated_at', $row)
        {{ \Carbon\Carbon::parse($row->market_data_updated_at)->diffForHumans() }}
    @endscope
</x-table>
