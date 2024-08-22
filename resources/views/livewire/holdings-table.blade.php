<?php

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component {

    // props
    public Portfolio $portfolio;

    public array $sortBy = ['column' => 'symbol', 'direction' => 'asc'];

    public array $headers;

    public function mount()
    {
    
        $this->headers = [
            ['key' => 'symbol', 'label' => __('Symbol'), 'class' => ''],
            ['key' => 'market_data_name', 'label' => __('Name'), 'sortable' => true],
            ['key' => 'quantity', 'label' => __('Quantity')],
            ['key' => 'average_cost_basis', 'label' => __('Average Cost Basis')],
            ['key' => 'total_cost_basis', 'label' => __('Total Cost Basis')],
            ['key' => 'market_data_market_value', 'label' => __('Market Value')],
            ['key' => 'total_market_value', 'label' => __('Total Market Value')],
            ['key' => 'market_gain_dollars', 'label' => __('Market Gain/Loss')],
            ['key' => 'market_gain_percent', 'label' => __('Market Gain/Loss')],
            ['key' => 'realized_gain_dollars', 'label' => __('Realized Gain/Loss')],
            ['key' => 'dividends_earned', 'label' => __('Dividends Earned')],
            ['key' => 'market_data_fifty_two_week_low', 'label' => __('52 week low')],
            ['key' => 'market_data_fifty_two_week_high', 'label' => __('52 week high')],
            ['key' => 'num_transactions', 'label' => __('Number of Transactions')],
            ['key' => 'market_data_updated_at', 'label' => __('Market Data Age')],
        ];
    }

    public function holdings(): Collection
    {
        return $this->portfolio
                    ->holdings()
                    ->withCount(['transactions as num_transactions' => function ($query) {
                        $query->portfolio($this->portfolio->id);
                    }])
                    ->withAggregate('market_data', 'name')
                    ->withAggregate('market_data', 'market_value')
                    ->withAggregate('market_data', 'fifty_two_week_low')
                    ->withAggregate('market_data', 'fifty_two_week_high')
                    ->withAggregate('market_data', 'updated_at')
                    ->selectRaw('(market_data.market_value * holdings.quantity) AS total_market_value')
                    ->selectRaw('((market_data.market_value - holdings.average_cost_basis) * holdings.quantity) AS market_gain_dollars')
                    ->selectRaw('(((market_data.market_value - holdings.average_cost_basis) / holdings.average_cost_basis) * 100) AS market_gain_percent')
                    ->join('market_data', 'holdings.symbol', 'market_data.symbol')
                    ->orderBy(...array_values($this->sortBy))
                    ->where('quantity', '>', 0)
                    ->get();
    }

}; ?>

<div class="">

    <x-table wire:loading.remove :headers="$headers" :rows="$this->holdings()" :sort-by="$sortBy" />
</div>