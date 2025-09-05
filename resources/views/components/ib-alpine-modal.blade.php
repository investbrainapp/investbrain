@props([
    'key' => 'modal',
    'showClose' => true,
    'closeOnEscape' => true,
    'title' => null,
    'subtitle' => null,
    'persistent' => false
])

<div>
    @teleport('body')
    <dialog 
        x-data="{ open: false }"
        x-on:toggle-{{ $key }}.window="open = !open"
        class="relative z-50 w-auto h-auto"
        @if($closeOnEscape)
            @keydown.window.escape="open = false"
        @endif
    >
        <template x-teleport="body">
            <div x-transition.opacity x-show="open" class="fixed top-0 left-0 z-[99] flex items-center justify-center w-full h-full">
                <div 
                    @if(!$persistent)
                        @click="open=false" 
                    @endif
                    class="absolute inset-0 w-full h-full bg-black bg-opacity-40"
                    x-show="open"
                    x-cloak
                ></div>

                <x-ib-card     
                    x-trap.inert.noscroll="open"
                    :title="$title"
                    :subtitle="$subtitle"
                    {{ $attributes->merge(['class' => 'relative transform overflow-hidden rounded-md ext-left shadow-xl w-full sm:w-2/3 lg:w-1/3 m-2 sm:m-0']) }} 
                    x-show="open"
                    x-cloak
                >
                    @if ($showClose)
                        <x-button 
                            icon="o-x-mark" 
                            title="{{ __('Close') }}"
                            class="absolute top-4 right-4 btn-ghost btn-circle btn-sm" 
                            @click="open = false" 
                        />
                    @endif

                    {{ $slot }}

                </x-ib-card>
            </div>
        </template>
    </dialog>
    @endteleport
</div>