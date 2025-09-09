@props([
    'value' => ''
])

<div {{ $attributes->class(["badge"])}}>
    {{ $slot ?? $value }}
</div>