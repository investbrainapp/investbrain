@props([
    'key' => 'modal',
    'title' => null,
    'subtitle' => null,
    'persistent' => false,
    'withoutTrapFocus' => false,
    'boxClass' => '',
    'noCard' => false,
    'shortcut' => null,
])

<template x-teleport="body">
    <dialog
        x-data="{ 
            @if (!empty($attributes->whereStartsWith('wire:model')->first()))
                init(){
                    this.$watch('wireModelValue', value => this.wireModelValue ? this.show() : this.close())
                },
                wireModelValue: $wire.entangle('{{ $attributes->whereStartsWith('wire:model')->first() }}').live,
            @endif
            open: false,
            close() {
                this.open = false;
                $el.close()
            },
            cancel() {
                @if($persistent)
                    this.$refs.modalContent.classList.add('wiggle')
                    this.$refs.modalContent.addEventListener('animationend', (e) => {
                        this.$refs.modalContent.classList.remove('wiggle')
                    })
                @else
                    this.close()
                @endif
            },
            show() {
                this.open = true;
                @if($persistent)
                    $el.showModal();
                @else
                    $el.show();
                @endif
            }
        }"

        @close="close()"
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

        @keydown.escape.prevent.stop="cancel()"

        @if(!$withoutTrapFocus)
            x-trap="open" 
            x-bind:inert="!open"
        @endif
    >
        {{-- BACKDROP --}}
        <div 
            @click.prevent.stop="cancel()" 
            class="absolute inset-0 w-full h-full bg-base-300/50"
        ></div>
        
        {{-- MODAL CONTENT --}}
        <div x-ref="modalContent" class="modal-box p-0 {{ $boxClass }}">

            @if(!$noCard)
                <x-ui.card     
                    :title="$title"
                    :subtitle="$subtitle"
                    expanded="true"      
                >
        
                @if (!$persistent && !$noCard)
                    <x-ui.button 
                        icon="o-x-mark" 
                        title="{{ __('Close') }}"
                        class="absolute top-4 right-4 btn-ghost btn-circle btn-sm z-10" 
                        @click="close()" 
                        tabindex="-999"
                    />
                @endif
                    
                    {{ $slot }}

                </x-ui.card>

            @else 

                {{ $slot }}

            @endif

        </div>
        
    </dialog>

</template> 