@props([
    'id' => Str::uuid()->toString(),
    'label' => null,
    'right' => false,
    'tight' => false,
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

<div>
    <label for="{{ $id }}" class="flex gap-3 items-center cursor-pointer">
        @if($right)
            <span @class(["flex-1" => !$tight])>
                {{ $label }}

                @if($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif
            </span>
        @endif

        <input
            id="{{ $id }}"
            type="checkbox"
            {{ $attributes->whereDoesntStartWith('id')->merge(['class' => 'checkbox checkbox-primary']) }}  />

        @if(!$right)
            {{ $label }}

            @if($attributes->get('required'))
                <span class="text-error">*</span>
            @endif
        @endif
    </label>

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
