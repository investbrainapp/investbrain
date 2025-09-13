@props([
    'key' => 'modal',
    'title' => null,
    'subtitle' => null,
    'persistent' => false,
    'withoutTrapFocus' => false,
    'boxClass' => '',
    'noCard' => false,
    'shortcut' => null
])

<template x-teleport="body">
    <dialog 
        x-data="{ 
            {{-- @if (!empty($attributes->whereStartsWith('wire:model')))
                open: $wire.entangle('confirmingTransactionDeletion').live,
            @else --}}
                open: false,
            {{-- @endif --}}
            close() {
                this.open = false;
                $el.close()
            },
            show() {
                this.open = true;
                $el.showModal();
            }
        }"

        :open="open"

        {{ 
            $attributes->filter(
                fn ($value, $key) => !Str::startsWith($key, 'wire:model')
            )->class(["modal z-50"]) 
        }}

        id="{{ $key }}"

        x-on:toggle-{{ $key }}.window="open ? close() : show();"

        @if($shortcut)
            @keydown.window.prevent.{{ $shortcut }}="show();"
        @endif

        @if($persistent)
            @keydown.escape.prevent.stop="()=>null"
        @endif

        @if(!$withoutTrapFocus)
            x-trap="open" 
            x-bind:inert="!open"
        @endif
    >
        {{-- BACKDROP --}}
        <div 
            @if(!$persistent)
                @click.prevent.stop="close()" 
            @endif
            class="absolute inset-0 w-full h-full bg-transparent"
        ></div>
        
        {{-- MODAL CONTENT --}}
        <div class="modal-box p-0 {{ $boxClass }}">

            @if(!$noCard)
                <x-ib-card     
                    :title="$title"
                    :subtitle="$subtitle"
                    expanded="true"      
                >
        
                @if (!$persistent && !$noCard)
                    <x-ib-button 
                        icon="o-x-mark" 
                        title="{{ __('Close') }}"
                        class="absolute top-4 right-4 btn-ghost btn-circle btn-sm z-10" 
                        @click="close()" 
                        tabindex="-999"
                    />
                @endif
                    
                    {{ $slot }}

                </x-ib-card>

            @else 

                {{ $slot }}

            @endif

        </div>
        
    </dialog>
</template> 