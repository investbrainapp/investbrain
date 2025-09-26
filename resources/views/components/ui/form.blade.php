@props([
    'noSeparator' => false,
])

<form
    {{ $attributes->whereDoesntStartWith('class') }}
    {{ $attributes->class(['grid grid-flow-row auto-rows-min gap-3']) }}
>

    {{ $slot }}

    @if ($actions)
        @if(!$noSeparator)
            <x-ui.section-border class="my-3" />
        @endif

        <div class="flex justify-end gap-3">
            {{ $actions}}
        </div>
    @endif
</form>