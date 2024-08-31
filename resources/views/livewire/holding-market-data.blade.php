<?php

use App\Models\Holding;
use Livewire\Volt\Component;

new class extends Component {

    // props
    public Holding $holding;

    protected $listeners = [
        'transaction-updated' => '$refresh',
        'transaction-saved' => '$refresh'
    ];
    
    // methods

}; ?>

<div>
    <div class="font-bold text-2xl py-1 flex items-center">
        {{ Number::currency($holding->market_data->market_value ?? 0) }} 
        
        <x-gain-loss-arrow-badge 
            :cost-basis="$holding->average_cost_basis"
            :market-value="$holding->market_data->market_value"
        />
    </div>

    <p>
        <span class="font-bold">{{ __('Quantity Owned') }}: </span>
        {{ $holding->quantity }} 
    </p>

    <p>
        <span class="font-bold">{{ __('Average Cost Basis') }}: </span>
        {{ Number::currency($holding->average_cost_basis ?? 0) }} 
    </p>

    <p>
        <span class="font-bold">{{ __('Total Cost Basis') }}: </span>
        {{ Number::currency($holding->total_cost_basis ?? 0) }} 
    </p>

    <p>
        <span class="font-bold">{{ __('Realized Gain/Loss') }}: </span>
        {{ Number::currency($holding->realized_gain_dollars ?? 0) }} 
    </p>

    <p>
        <span class="font-bold">{{ __('Dividends Earned') }}: </span>
        {{ Number::currency($holding->dividends_earned ?? 0) }} 
    </p>

    <p class="pt-2 text-sm">
        {{ __('Market Data Age') }}: 
        {{ \Carbon\Carbon::parse($holding->market_data->updated_at)->diffForHumans() }}
    </p>
</div>