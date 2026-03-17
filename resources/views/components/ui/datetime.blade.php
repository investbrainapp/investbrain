@props([
    'id' => null,
    'label' => null,
    'icon' => null,
    'hint' => null,
    'hintClass' => 'label-text-alt text-gray-400 py-1 pb-0',

    'errorField' => null,
    'errorClass' => 'text-red-500 label-text-alt p-1',
    'omitError' => false,
    'firstErrorOnly' => false,
])

@php
    $modelName = $attributes->whereStartsWith('wire:model')->first();
    $errorFieldName = $errorField ?? $modelName;
    $id = $id == $modelName ? $modelName : "{$id}{$modelName}";
@endphp

<style>
    input[type="date"]::-webkit-calendar-picker-indicator {
        color: transparent;
        background: transparent;
        display: none;
    }
</style>

<div x-data>
    {{-- STANDARD LABEL --}}
    @if($label)
        <label for="{{ $id }}" class="pt-0 label label-text font-semibold">
            <span>
                {{ $label }}

                @if($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif
            </span>
        </label>
    @endif

    <div class="flex-1 relative">
        <input
            type="date"
            placeholder="Select date"
            id="{{ $id }}"
            x-ref="dateInput"
            onfocus="this.showPicker?.()"

            {{ $attributes->class([
                    "block input input-primary w-full peer appearance-none",
                    'ps-10' => ($icon),
                    'border border-dashed' => $attributes->has('readonly') && $attributes->get('readonly') == true,
                    'input-error' => $errors->has($errorFieldName)
                ]) }}
        />

        {{-- ICON --}}
        <div @click="$refs.dateInput.showPicker?.()"
            class="z-60 absolute top-1/2 -translate-y-1/2 end-0 p-3 cursor-pointer text-neutral-400 hover:text-neutral-500"
        >
            <x-ui.icon name="o-calendar" />
        </div>
    </div>

    {{-- ERROR --}}
    @if(!$omitError && $errors->has($errorFieldName))
        @foreach($errors->get($errorFieldName) as $message)
            @foreach(Arr::wrap($message) as $line)
                <div class="{{ $errorClass }}" x-classes="text-red-500 label-text-alt p-1">{{ $line }}</div>
                @break($firstErrorOnly)
            @endforeach
            @break($firstErrorOnly)
        @endforeach
    @endif

    {{-- HINT --}}
    @if($hint)
        <div class="{{ $hintClass }}" x-classes="label-text-alt text-gray-400 py-1 pb-0">{{ $hint }}</div>
    @endif

</div>
