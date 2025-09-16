@props([
    'type' => 'button',
    'external' => false,
    'link' => null,
    'label' => null,
    'icon' => null,
    'spinner' => null,
    'tooltip' => null,
    'tooltipLeft' => null,
    'tooltipRight' => null,
    'tooltipBottom' => null,
    'badge' => null,
    'badgeClasses' => null,
])

@php
    $tooltip = $tooltip ?? $tooltipLeft ?? $tooltipRight ?? $tooltipBottom;
    $tooltipPosition = $tooltipLeft ? 'lg:tooltip-left' : ($tooltipRight ? 'lg:tooltip-right' : ($tooltipBottom ? 'lg:tooltip-bottom' : 'lg:tooltip-top'));
    $spinnerTarget = $spinner ?? $attributes->whereStartsWith('wire:click')->first();
@endphp

@if($link)
    <a href="{!! $link !!}"
@else
    <button
@endif
    {{ $attributes->whereDoesntStartWith('class')->merge(['type' => $type]) }}
    type="button" 
    {{ $attributes->class(['btn', "!inline-flex lg:tooltip $tooltipPosition" => $tooltip]) }} 

    @if($link && $external)
        target="_blank"
    @endif

    @if($link && !$external)
        wire:navigate
    @endif

    data-tip="{{ $tooltip }}"

    @if($spinner)
        wire:target="{{ $spinnerTarget }}"
        wire:loading.attr="disabled"
    @endif
>

    {{-- spinner --}}
    @if($spinner)
        <span wire:loading wire:target="{{ $spinnerTarget }}" class="loading loading-spinner w-5 h-5">test</span>
    @endif
    
    {{-- icon --}}
    @if($icon)
        <span class="block" @if($spinner) wire:loading.class="hidden" wire:target="{{ $spinnerTarget }}" @endif>
            <x-ui.icon :name="$icon" />
        </span>
    @endif
    
    {{-- label / slot --}}
    @if($label)
        <span>
            {{ $label }}
        </span>
        @if(strlen($badge ?? '') > 0)
            <span class="badge badge-sm {{ $badgeClasses }}">{{ $badge }}</span>
        @endif
    @else
        {{ $slot }}
    @endif
    
@if($link)
    </a>
@else
    </button>
@endif