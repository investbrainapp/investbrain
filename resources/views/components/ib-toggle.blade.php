@props([
    'id' => Str::uuid()->toString(),
    'label' => null,
    'right' => false,
    'hint' => null,
    'hintClass' => 'text-xs label-text-alt fieldset-label',
    
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
    <fieldset class="fieldset">
        <div class="w-full">
            <label @class(["flex gap-3 items-center cursor-pointer select-none", "justify-between" => $right, "!items-start" => $hint])>

                {{-- TOGGLE --}}
                <input
                    id="{{ $id }}"
                    type="checkbox"

                    {{
                        $attributes->whereDoesntStartWith('id')
                            ->class(["order-2" => $right])
                            ->merge(['class' => 'toggle'])
                    }}
                />

                {{-- LABEL --}}
                    <div @class(["order-1" => $right])>
                    <div class="text-sm font-medium">
                        {{ $label }}

                        @if($attributes->get('required'))
                            <span class="text-error">*</span>
                        @endif
                    </div>

                    {{-- HINT --}}
                    @if($hint)
                        <div class="{{ $hintClass }}" x-classes="fieldset-label">{{ $hint }}</div>
                    @endif
                </div>
            </label>
        </div>

        {{-- ERROR --}}
        @if(!$omitError && $errors->has($errorFieldName))
            @foreach($errors->get($errorFieldName) as $message)
                @foreach(Arr::wrap($message) as $line)
                    <div class="{{ $errorClass }}" x-class="text-error">{{ $line }}</div>
                    @break($firstErrorOnly)
                @endforeach
                @break($firstErrorOnly)
            @endforeach
        @endif
    </fieldset>
</div>
