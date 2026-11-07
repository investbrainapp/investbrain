@props([
    'id' => null,
    'title' => null,
    'icon' => null,
    'description' => null,
    'shadow' => false,
    'dismissable' => false,
])

<div
    wire:key="{{ $id }}"
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->class(['alert rounded-md', 'shadow-md' => $shadow])}}
    x-data="{ show: true }" x-show="show"
>
    @if($icon)
        <x-ui.icon :name="$icon" class="self-center" />
    @endif

    @if($title)
        <div>
            <div @class(["font-bold" => $description])>{{ $title }}</div>
            <div class="text-xs">{{ $description }}</div>
        </div>
    @else
        <span>{{ $slot }}</span>
    @endif

    @isset($actions)
        <div class="flex items-center gap-3">
            {{ $actions }}
        </div>
    @endisset

    @if($dismissable)
        <x-ui.button icon="o-x-mark" @click="show = false" class="btn-xs btn-circle btn-ghost static self-start end-0" />
    @endif
</div>
