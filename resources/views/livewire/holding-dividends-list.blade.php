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
    @foreach ($holding->dividends->take(5) as $dividend)

    <x-list-item :item="$dividend">
        <x-slot:value>
        
            @php
                $owned = ($dividend->purchased - $dividend->sold);
            @endphp 

            {{ Number::currency($dividend->dividend_amount) }}
            x {{ $owned }}
            = {{ Number::currency($owned * $dividend->dividend_amount) }}

        </x-slot:value>
        <x-slot:sub-value>
            <span title="{{ __('Ex Dividend Date') }}">{{ $dividend->date->format('F d, Y') }}</span>
        </x-slot:sub-value>
    </x-list-item>

    @endforeach
</div>