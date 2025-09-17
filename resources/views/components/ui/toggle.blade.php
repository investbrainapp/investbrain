@props([
    'id' => null,
    'label' => null,
    'right' => false,
    'hint' => null,
    'hintClass' => 'label-text-alt text-gray-400 py-1 pb-0',
    'tight' => false,
    
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
    <label for="{{ $id }}" class="flex items-center gap-3 cursor-pointer font-semibold">

        @if($right)
            <span @class(["flex-1" => !$tight])>
                {{ $label}}

                @if($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif
            </span>
        @endif

        <input id="{{ $id }}" type="checkbox" {{ $attributes->whereDoesntStartWith('class') }} {{ $attributes->class(['toggle toggle-primary']) }}  />

        @if(!$right)
            {{ $label}}

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