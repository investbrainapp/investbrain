@props([
    'value' => 0,
    'max' => 100,
    'indeterminate' => null,
])

<progress
    {{ $attributes->class("progress") }}

    @if(!$indeterminate)
        value="{{ $value }}"
        max="{{ $max }}"
    @endif
></progress>