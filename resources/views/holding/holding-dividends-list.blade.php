<?php

use App\Models\Holding;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public Holding $holding;

    protected $listeners = [
        'transaction-updated' => '$refresh',
        'transaction-saved' => '$refresh',
    ];

    // methods

}; ?>

<div>
    @foreach ($holding->dividends->take(5) as $dividend)

    <x-ui.list-item :item="$dividend" no-separator no-hover>
        <x-slot:value>
        
            {{ Number::currency($dividend->dividend_amount, $holding->market_data->currency) }}
            x {{ $dividend->shares_owned }}
            = {{ Number::currency($dividend->total_received, $holding->market_data->currency) }}

        </x-slot:value>
        <x-slot:sub-value>
            <span title="{{ __('Ex Dividend Date') }}">{{ $dividend->date->format('F d, Y') }}</span>
        </x-slot:sub-value>
    </x-ui.list-item>

    @endforeach
</div>