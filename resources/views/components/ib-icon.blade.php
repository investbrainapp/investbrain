@props([
    'id' => Str::uuid()->toString(),
    'name' => null,
    'label' => null,
])

@php
    $name = Str::of($name);

    $icon = $name->contains('.') ? $name->replace('.', '-') : "heroicon-{$name}";

    // Remove `w-*` and `h-*` classes, because it applies only for icon
    $labelClasses = Str::replaceMatches('/(w-\w*)|(h-\w*)/', '', $attributes->get('class') ?? '');
@endphp

@if(strlen($label ?? '') > 0)
    <div class="inline-flex items-center gap-1">
@endif
        <x-icon :name="$icon"
            {{
                $attributes->class([
                    'inline',
                    'w-5 h-5' => !Str::contains($attributes->get('class') ?? '', ['w-', 'h-'])
                ])
            }}
        />

@if(strlen($label ?? '') > 0)
        <div class="{{ $labelClasses }}">
            {{ $label }}
        </div>
    </div>
@endif