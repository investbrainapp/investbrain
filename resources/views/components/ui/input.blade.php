@props([
    'id' => Str::uuid()->toString(),
    'label' => null,
    'icon' => null,
    'hint' => null,
    'hintClass' => 'label-text-alt text-gray-400 py-1 pb-0',
    'prefix' => null,
    'suffix' => null,
    'locale' => 'en-US',

    'prepend' => null,
    'append' => null,

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

    {{-- PREFIX/SUFFIX/PREPEND/APPEND CONTAINER --}}
    @if($prefix || $suffix || $prepend || $append)
        <div class="flex">
    @endif

    {{-- PREFIX / PREPEND --}}
    @if($prefix || $prepend)
        <div
            @class([
                    "rounded-s-lg flex items-center bg-base-200",
                    "border border-primary border-e-0 px-4" => $prefix,
                    "border-0 bg-base-300" => $attributes->has('disabled') && $attributes->get('disabled') == true,
                    "border-dashed" => $attributes->has('readonly') && $attributes->get('readonly') == true,
                    "!border-error" => $errorFieldName && $errors->has($errorFieldName) && !$omitError
                ])
        >
            {{ $prepend ?? $prefix }}
        </div>
    @endif

    <div class="flex-1 relative">

        {{-- INPUT --}}
        <input
            id="{{ $id }}"
            placeholder = "{{ $attributes->whereStartsWith('placeholder')->first() }} "

            @if($attributes->has('autofocus') && $attributes->get('autofocus') == true)
                autofocus
            @endif

            {{
                $attributes
                    ->merge(['type' => 'text'])
                    ->class([
                        'input input-primary w-full peer',
                        'ps-10' => ($icon),
                        'rounded-s-none' => $prefix || $prepend,
                        'rounded-e-none' => $suffix || $append,
                        'border border-dashed' => $attributes->has('readonly') && $attributes->get('readonly') == true,
                        'input-error' => $errorFieldName && $errors->has($errorFieldName) && !$omitError
                ])
            }}
        />

        {{-- ICON  --}}
        @if($icon)
            <x-ui.icon :name="$icon" class="absolute top-1/2 -translate-y-1/2 start-3 text-gray-400 pointer-events-none" />
        @endif
    </div>

    {{-- SUFFIX/APPEND --}}
    @if($suffix || $append)
            <div
            @class([
                    "rounded-e-lg flex items-center bg-base-200",
                    "border border-primary border-s-0 px-4" => $suffix,
                    "border-0 bg-base-300" => $attributes->has('disabled') && $attributes->get('disabled') == true,
                    "border-dashed" => $attributes->has('readonly') && $attributes->get('readonly') == true,
                    "!border-error" => $errorFieldName && $errors->has($errorFieldName) && !$omitError
                ])
        >
            {{ $append ?? $suffix }}
        </div>
    @endif

    {{-- END: PREFIX/SUFFIX/APPEND/PREPEND CONTAINER  --}}
    @if($prefix || $suffix || $prepend || $append)
        </div>
    @endif

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