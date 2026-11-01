<?php

use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public Collection $holdings;

    // methods

}; ?>

<div class="">

    @foreach(
        $holdings->sortByDesc('market_gain_percent')
                    ->where('quantity', '>', 0)
                    ->where('market_data.market_value', '>', 0)
                    ->take(5) 
        as $holding
    )
        <x-ui.list-item 
            no-separator 
            :item="$holding" 
            link="{{ route('holding.show', [
                'portfolio' => $holding->portfolio_id,
                'symbol' => $holding->symbol,
            ]) }}"
        >

            <x-slot:value>
                {{ $holding->market_data?->name }} ({{ $holding->symbol }})
            </x-slot:value>
            <x-slot:sub-value>
                {{ $holding->portfolio->title }}
            </x-slot:sub-value>
            {{-- Keep the % badge outside the truncated name so long titles don't hide performance --}}
            <x-slot:actions>
                <x-ui.gain-loss-arrow-badge
                    :cost-basis="$holding->average_cost_basis"
                    :market-value="$holding->market_data->market_value"
                />
            </x-slot:actions>
        </x-ui.list-item>
    @endforeach
</div>