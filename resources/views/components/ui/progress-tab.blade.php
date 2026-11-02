@props([
    'title' => null,
    'message' => null,
    'progress' => null,
    'failed' => false,
    'href' => null,
])

{{-- offset from the right edge so it never covers the AI chat button --}}
<div class="fixed bottom-0 right-24 md:right-28 z-40 w-60 md:w-72">
    @if($href)
        <a href="{{ $href }}" wire:navigate {{ $attributes->class('block') }}>
    @else
        <button type="button" {{ $attributes->class('block w-full text-start cursor-pointer') }}>
    @endif

        <div @class([
            'rounded-t-md border border-b-0 shadow-lg px-4 pt-3 pb-4 bg-base-100',
            'border-base-300' => ! $failed,
            'border-error' => $failed,
        ])>
            <div @class(['flex items-center gap-2 text-sm', 'font-semibold' => $title])>
                @if($failed)
                    <x-ui.icon name="o-exclamation-triangle" class="w-4 h-4 text-error" />
                @else
                    <span class="loading loading-spinner loading-xs"></span>
                @endif

                <span class="truncate">{{ $title ?? $message }}</span>
            </div>

            @if($title)
                <div class="mt-1 text-xs text-secondary-content truncate">{{ $message }}</div>
            @endif

            @unless($failed)
                <x-ui.progress
                    :indeterminate="is_null($progress)"
                    class="progress-primary h-1.5 mt-2"
                    value="{{ $progress ?? 0 }}"
                    max="100"
                />
            @endunless
        </div>

    @if($href)
        </a>
    @else
        </button>
    @endif
</div>
