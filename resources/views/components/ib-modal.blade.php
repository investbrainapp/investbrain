@props([
    'key' => 'modal',
    'title' => null,
    'subtitle' => null,
    'persistent' => false,
    'withoutTrapFocus' => false,
    'boxClass' => '',
    'cardOptions' => [
        'noPadding' => false,
        'noShadow' => false
    ],
    'shortcut' => null
])

<template x-teleport="body">
    <dialog 
        x-data="{ 
            open: false,  
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
        
        {{ $attributes->except('wire:model')->class(["modal z-50"]) }}

        id="{{ $key }}"

        x-on:toggle-{{ $key }}.window="show();"

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
            class="absolute inset-0 w-full h-full"
        ></div>
        
        {{-- MODAL CONTENT --}}
        <div class="modal-box {{ $boxClass }}">

            <x-ib-card     
                :title="$title"
                :subtitle="$subtitle"
                dense="true"
                no-padding="{{ $cardOptions['noPadding'] }}"
                no-shadow="{{ $cardOptions['noShadow'] }}"
            >
                @if (!$persistent)
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
        </div>
        
    </dialog>
</template> 