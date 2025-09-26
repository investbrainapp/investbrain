@props([
    'id' => null,
    'image' => '',
    'alt' => '',
    'placeholder' => '',
    'fallbackImage' => null,

    'title' => null,
    'subtitle' => null,
])

<div class="flex items-center gap-3">
    <div class="avatar @if(empty($image)) avatar-placeholder @endif">
        <div {{ $attributes->class(["w-7 rounded-full", "bg-neutral text-neutral-content" => empty($image)]) }}>
            @if(empty($image))
                <span class="text-xs" alt="{{ $alt }}">{{ $placeholder }}</span>
            @else
                <img src="{{ $image }}" alt="{{ $alt }}" @if($fallbackImage) onerror="this.src='{{ $fallbackImage }}'" @endif />
            @endif
        </div>
    </div>
    @if($title || $subtitle)
    <div>
        @if($title)
            <div @class(["font-semibold font-lg", is_string($title) ? '' : $title?->attributes->get('class') ]) >
                {{ $title }}
            </div>
        @endif
        @if($subtitle)
            <div @class(["text-sm text-base-content/50", is_string($subtitle) ? '' : $subtitle?->attributes->get('class') ]) >
                {{ $subtitle }}
            </div>
        @endif
    </div>
    @endif
</div>
