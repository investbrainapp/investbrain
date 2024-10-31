@props([
    'showClose' => true,
    'closeOnEscape' => true,
    'title' => null,
    'subtitle' => null,
    'persistent' => false
])

<div>
    @teleport('body')
    <dialog
        {{ $attributes->except('wire:model')->class(["modal"]) }}
        x-data="{open: @entangle($attributes->wire('model')).live }"
        :class="{'modal-open !animate-none': open}"
        :open="open"
        @if($closeOnEscape)
            @keydown.escape.window = "$wire.{{ $attributes->wire('model')->value() }} = false"
        @endif
    >
        <x-card     
            :title="$title"
            :subtitle="$subtitle"
            {{ $attributes->merge(['class' => 'modal-box relative transform overflow-hidden rounded-md ext-left shadow-xl w-full sm:w-2/3 lg:w-1/3 m-2 sm:m-0']) }} 
        >
            @if ($showClose)
                <x-button 
                    icon="o-x-mark" 
                    title="{{ __('Close') }}"
                    class="absolute top-4 right-4 btn-ghost btn-circle btn-sm" 
                    @click="$wire.{{ $attributes->wire('model')->value() }} = false"
                />
            @endif

            {{ $slot }}

        </x-card>

        <div class="modal-backdrop" method="dialog">
            <a 
                @if(!$persistent)
                    @click="$wire.{{ $attributes->wire('model')->value() }} = false"
                @endif
                type="button"
                title="{{ __('Close') }}"
            >
                {{ __('Close') }}
            </a>
        </div>
    </dialog>
    @endteleport
</div>