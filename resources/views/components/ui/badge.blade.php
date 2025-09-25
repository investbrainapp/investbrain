@props([
    'value' => null,
])

@php
    if (isset($class)) {
        $attributes->setAttributes(['class' => $class]);
    }
@endphp

<div {{ $attributes->class(["badge select-none"]) }}>
    {{ $value ?? $slot ?? '' }}
</div>