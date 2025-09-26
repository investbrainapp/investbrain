@props([
    'id' => null,
    'label' => null,
    'icon' => 'o-chevron-down',

    'trigger' => null,
])

<details 
    x-data="{
        dropdownOpen: false
    }"
    :open="dropdownOpen"
    @click.outside="dropdownOpen = false"
    @class(['dropdown'])
>
    {{-- CUSTOM TRIGGER --}}
    @if($trigger)
        <summary x-ref="button" @click.prevent="dropdownOpen = !dropdownOpen" {{ $trigger->attributes->class(['list-none']) }}>
            {{ $trigger }}
        </summary>
    @else
        {{-- DEFAULT TRIGGER --}}
        <summary 
            x-ref="button" 
            @click.prevent="dropdownOpen = !dropdownOpen" 
            {{ $attributes->class(["btn btn-ghost normal-case disabled:opacity-50 disabled:pointer-events-none"]) }}
        >
            {{ $label }}
            <span class="transition-transform" :class="{'rotate-180': dropdownOpen }">
                <x-ui.icon :name="$icon" />
            </span>
        </summary>
    @endif

    {{-- CONTENT --}}
    <ul
        @class([
            'menu',
            'absolute',
            'top-0',
            'p-2',
            'shadow-lg',
            'z-50',
            'bg-base-100',
            'rounded-box',
            'w-auto',
            'min-w-max',
        ])
        x-anchor.bottom-start="$refs.button"
        @click="dropdownOpen = false"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="-translate-y-2"
        x-transition:enter-end="translate-y-0"
        x-cloak
    >
        <div wire:key="dropdown-slot-{{ $id }}">
            {{ $slot }}
        </div>
    </ul>

</details>