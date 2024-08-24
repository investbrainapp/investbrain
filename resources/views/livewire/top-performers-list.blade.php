<?php

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component {

    // props
    public Collection $holdings;

    // methods
    
}; ?>

<div class="">

    @foreach(
        $holdings->sortBy('market_gain_percent')
                    ->where('quantity', '>', 0)
                    ->where('market_data.market_value', '>', 0)
                    ->take(5) 
        as $holding
    )
        <x-list-item 
            no-separator 
            :item="$holding" 
            link="{{ route('portfolio.show', ['portfolio' => $holding->portfolio_id]) }}"
        >

            @php
                $gainPercent = (($holding->market_data->market_value - $holding->average_cost_basis) / $holding->average_cost_basis) * 100;
            @endphp

            <x-slot:value class="flex items-center">

                {{ $holding->market_data?->name }} ({{ $holding->symbol }})
                
                <x-badge class="{{ $gainPercent > 0 ? 'badge-success' : 'badge-error' }} ml-2 badge-sm" >
                    <x-slot:value>
                        {{ Number::percentage($gainPercent) }} 
                    </x-slot:value>
                </x-badge>
                
            </x-slot:value>
            <x-slot:sub-value>
                {{ $holding->portfolio->title }}
            </x-slot:sub-value>
        </x-list-item>
    @endforeach
</div>