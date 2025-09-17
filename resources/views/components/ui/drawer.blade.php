@props([
    'key' => 'drawer',
    'showClose' => true,
    'closeOnEscape' => true,
    'title' => null,
    'subtitle' => null
])

<div 
    x-data="{ open: false }" 
    x-on:toggle-{{ $key }}.window="open = !open"
    @if($closeOnEscape)
        @keydown.window.escape="open = false"
    @endif
    x-trap="open" 
    x-bind:inert="!open"
    class="fixed inset-0 flex justify-end z-50" 
    x-cloak
>

    {{-- overlay --}}
    <div @click="open = false" x-show="open" class="z-40 fixed inset-0 bg-black opacity-50"></div>

    {{-- content --}}
    <div 
        class="transition duration-200 ease-out transition-transform translate-x-full transform z-50 md:w-3/4 xl:w-3/5" 
        :class="{'translate-x-0': open, 'translate-x-full': !open}"
    >
        <x-ui.card
            {{ $attributes->merge(['class' => 'w-full min-h-screen rounded-none px-8 overflow-y-scroll']) }} 
        >
            @if($title)
                <x-slot:title>
                    {!! strip_tags($title) !!}
                </x-slot:title>
            @endif

            @if($subtitle)
                <x-slot:subtitle>
                    {!! strip_tags($subtitle) !!}
                </x-slot:subtitle>
            @endif

            @if ($showClose)
                <x-ui.button icon="o-x-mark" title="{{ __('Close') }}" class="btn-ghost btn-circle btn-sm absolute top-4 right-4 " @click="open = false" />
            @endif

            {{ $slot }}

        </x-ui.card>
    </div>
</div>