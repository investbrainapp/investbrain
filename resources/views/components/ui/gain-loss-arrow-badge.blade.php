@props([
    'small' => false,
    'percent' => null,
    'costBasis' => null,
    'marketValue' => null
])

@php
    if (!is_null($percent)) {

        $isUp = $percent > 0;

    } else {

        $isUp = $costBasis <= $marketValue;
        $percent = $costBasis ? (($marketValue - $costBasis) / $costBasis) * 100 : 0;
    }
@endphp

@if(!empty($percent))

    <x-ui.badge
        class="{{ $small ? 'badge-xs' : 'badge-sm' }} {{ $isUp ? 'badge-success' : 'badge-error' }} badge-outline ml-2"
        title="{{ Number::percentage(
            $percent,
            $percent < 1 ? 2 : 0
        ) }}"
    >
        <x-slot:value>
            {!! $isUp ?  '&#9650;' :'&#9660;' !!}
            {{ Number::percentage(
                abs($percent),
                ($percent && $small) < 1 ? 2 : 0
            ) }}
        </x-slot:value>
    </x-ui.badge>
    
@endif