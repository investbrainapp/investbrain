@props([
    'key' => 'modal',
    'title' => null,
    'subtitle' => null,
    'persistent' => false,
    'withoutTrapFocus' => false,
    'boxClass' => '',
    'noPadding' => false
])

<template x-teleport="body">
    <dialog 
        {{ $attributes->except('wire:model')->class(["modal z-50"]) }}

        id="{{ $key }}"

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
                @click.prevent.stop="{{ $key }}?.close()" 
            @endif
            class="absolute inset-0 w-full h-full"
        ></div>
        
        {{-- MODAL CONTENT --}}
        <div class="modal-box {{ $boxClass }}">

            <x-ib-card     
                :title="$title"
                :subtitle="$subtitle"
                dense="true"
                no-padding="{{ $noPadding }}"
            >
                @if (!$persistent)
                    <x-ib-button 
                        icon="o-x-mark" 
                        title="{{ __('Close') }}"
                        class="absolute top-4 right-4 btn-ghost btn-circle btn-sm z-90" 
                        @click="{{ $key }}?.close()" 
                    />
                @endif
                
                {{ $slot }}
   
            </x-ib-card>
        </div>
        
    </dialog>
</template> 