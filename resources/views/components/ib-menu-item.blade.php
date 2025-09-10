@props([
    'id' => Str::uuid()->toString(),
    'title' => null,
    'icon' => null,
    'spinner' => null,
    'link' => null,
    'route' => null,
    'external' => false,
    'noWireNavigate' => false,
    'badge' => null,
    'badgeClasses' => null,
    'badge' => false,
    'separator' => false,
    'enabled' => true,
    'active' => false,
    'exact' => false
])

@aware(['activateByRoute' => false, 'activeBgColor' => 'bg-base-100'])

@php
    $spinnerTarget = $spinner == true ? $attributes->whereStartsWith('wire:click')->first() : $spinner;

    if ($link == null) {
        
        $routeMatches = false;

    } else if ($route) {

        $routeMatches = request()->routeIs($route);

    } else {

        $link = url($link ?? '');
        $route = url(request()->url());

        if ($link == $route) {

            $routeMatches = true;

        } else {
            
            $routeMatches = ! $exact && $link != '/' && Str::startsWith($route, $link);
        }
    }

@endphp

@if (!$enabled) 
    {{-- DISABLED --}}
@else
    {{-- ENABLED --}}
    <li 
        title="{{ $title }}" 
        {{
            $attributes->class([
                "my-0.5 hover:text-inherit rounded-md",
                "$activeBgColor" => ($active || ($activateByRoute && $routeMatches))
            ])
        }}
    >
        <a
            @if($link)
                href="{{ $link }}"

                @if($external)
                    target="_blank"
                @endif

                @if(!$external && !$noWireNavigate)
                    {{ $attributes->wire('navigate')->value() ? $attributes->wire('navigate') : 'wire:navigate' }}
                @endif
            @endif

            @if($spinner)
                wire:target="{{ $spinnerTarget }}"
                wire:loading.attr="disabled"
            @endif
        >
            <!-- SPINNER -->
            @if($spinner)
                <span wire:loading wire:target="{{ $spinnerTarget }}" class="loading loading-spinner w-5 h-5"></span>
            @endif

            @if($icon)
                <span class="block -mt-0.5" @if($spinner) wire:loading.class="hidden" wire:target="{{ $spinnerTarget }}" @endif>
                    <x-ib-icon :name="$icon" />
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
