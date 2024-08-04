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
    x-show="open"
    @if($closeOnEscape)
        @keydown.window.escape="open = false"
    @endif
    x-trap="open" 
    x-bind:inert="!open"
    class="fixed inset-0 flex justify-end z-50" 
    x-transition.opacity
    x-cloak
>

    <div @click="open = false" class="fixed inset-0 bg-black opacity-50"></div>

    <x-card 
        :title="$title"
        :subtitle="$subtitle"
        {{ $attributes->merge(['class' => 'min-h-screen w-11/12 lg:w-1/3 rounded-none px-8 transition']) }} 
    >

        @if ($showClose)
            <x-button icon="o-x-mark" class="btn-ghost btn-circle btn-sm absolute top-4 right-4 " @click="open = false" />
        @endif

        {{ $slot }}

    </x-card>
</div>