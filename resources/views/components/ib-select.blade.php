{{-- 
    label="{{ __('Portfolio') }}" 
    :options="auth()->user()->portfolios()->fullAccess()->get()"
    placeholder="Select a portfolio"
    option-value="locale"
    option-label="label"
    id="locale"
--}}


@props([
    'id' => Str::uuid()->toString(),
    'label' => null,
    'icon' => null,
    'hint' => null,
    'hintClass' => 'label-text-alt text-gray-400 ps-1 mt-2',
    'placeholder' => null,
    'optionValue' => 'id',
    'optionLabel' => 'name',
    'options' => array(),

    'prepend' => null,
    'append' => null,

    'errorField' => null,
    'errorClass' => '',
    'omitError' => false,
    'firstErrorOnly' => false,
])

@php
    $modelName = $attributes->whereStartsWith('wire:model')->first();
    $errorFieldName = $errorField ?? $modelName;
    $id = $id . $modelName;
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

    {{-- PREPEND/APPEND CONTAINER --}}
    @if($prepend || $append)
        <div class="flex">
    @endif

    {{-- PREPEND --}}
    @if($prepend)
        <div class="rounded-s-lg flex items-center bg-base-200">
            {{ $prepend }}
        </div>
    @endif

    <div class="relative flex-1">
        <select
            id="{{ $id }}"
            {{ $attributes->whereDoesntStartWith('class') }}
            {{ $attributes->class([
                    'select select-primary w-full font-normal',
                    'ps-10' => ($icon),
                    'rounded-s-none' => $prepend,
                    'rounded-e-none' => $append,
                    'border border-dashed' => $attributes->has('readonly') && $attributes->get('readonly') == true,
                    'select-error' => $errors->has($errorFieldName)
                ])
            }}

        >
            @if($placeholder)
                <option value="{{ $placeholderValue }}">{{ $placeholder }}</option>
            @endif

            @foreach ($options as $option)
                <option value="{{ data_get($option, $optionValue) }}" @if(data_get($option, 'disabled')) disabled @endif>{{ data_get($option, $optionLabel) }}</option>
            @endforeach
        </select>

        {{-- ICON --}}
        @if($icon)
            <x-ib-icon :name="$icon" class="absolute pointer-events-none top-1/2 -translate-y-1/2 start-3 text-gray-400" />
        @endif

    </div>

    {{-- APPEND --}}
    @if($append)
        <div class="rounded-e-lg flex items-center bg-base-200">
            {{ $append }}
        </div>
    @endif

    {{-- END: APPEND/PREPEND CONTAINER  --}}
    @if($prepend || $append)
        </div>
    @endif

    {{-- ERROR --}}
    @if(!$omitError && $errors->has($errorFieldName))
        @foreach($errors->get($errorFieldName) as $message)
            @foreach(Arr::wrap($message) as $line)
                <div class="text-red-500 label-text-alt p-1" x-classes="text-red-500 label-text-alt p-1">{{ $line }}</div>
                @break($firstErrorOnly)
            @endforeach
            @break($firstErrorOnly)
        @endforeach
    @endif

    {{-- HINT --}}
    @if($hint)
        <div class="{{ $hintClass }}" x-classes="label-text-alt text-gray-400 ps-1 mt-2">{{ $hint }}</div>
    @endif
</div>