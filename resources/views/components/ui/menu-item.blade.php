@props([
    'id' => null,
    'title' => null,
    'icon' => null,
    'spinner' => null,
    'link' => null,
    'route' => null,
    'external' => false,
    'badge' => null,
    'badgeClasses' => null,
    'badge' => false,
    'separator' => false,
    'enabled' => true,
])

@aware(['activateByRoute' => false, 'activeBgColor' => 'bg-neutral text-neutral-content'])

@php
    $spinnerTarget = $spinner == true ? $attributes->whereStartsWith('wire:click')->first() : $spinner;
@endphp

@if (!$enabled) 
    {{-- DISABLED --}}
@else
    {{-- ENABLED --}}
    <li 
        title="{{ $title }}" 
        {{ $attributes->class(["my-0.5 hover:text-inherit rounded-md"]) }}
    >
        <a
            @if($link)
                href="{{ $link }}"

                @if($activateByRoute)
                    wire:current="{{ $activeBgColor }}"
                @endif

                @if($external)
                    target="_blank"
                @endif

                @if(!$external)
                    {{ $attributes->wire('navigate')->value() ? $attributes->wire('navigate') : 'wire:navigate' }}
                @endif
            @endif

            @if($spinner)
                wire:target="{{ $spinnerTarget }}"
                wire:loading.attr="disabled"
            @endif
        >
            {{-- SPINNER --}}
            @if($spinner)
                <span wire:loading wire:target="{{ $spinnerTarget }}" class="loading loading-spinner w-5 h-5"></span>
            @endif

            @if($icon)
                <span class="block -mt-0.5" @if($spinner) wire:loading.class="hidden" wire:target="{{ $spinnerTarget }}" @endif>
                    <x-ui.icon :name="$icon" />
                </span>
            @endif

            @if($title || $slot->isNotEmpty())
            <span class="whitespace-nowrap">
                @if($title)
                    {{ $title }}

                    @if($badge)
                        <span class="badge badge-sm ml-2 {{ $badgeClasses }}">{{ $badge }}</span>
                    @endif
                @else
                    {{ $slot }}
                @endif
            </span>
            @endif
        </a>
    </li>
@endif
