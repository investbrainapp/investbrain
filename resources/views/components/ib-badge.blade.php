@props([
    'value' => null
])

<div {{ $attributes->class(["badge select-none"])}}>
    {{ $value ?? $slot  }}
</div>