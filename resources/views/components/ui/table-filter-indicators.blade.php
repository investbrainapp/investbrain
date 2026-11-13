{{-- Filament's stock filter indicators, moved from their own bar into the table toolbar so they sit beside the filter trigger. --}}
@php
    $filterIndicators = $table->getFilterIndicators();
@endphp

@if ($filterIndicators)
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <span class="text-sm leading-6 font-medium whitespace-nowrap text-gray-700 dark:text-gray-200">
            {{ __('filament-tables::table.filters.indicator') }}
        </span>

        <div class="flex flex-wrap gap-1.5" role="list">
            @foreach ($filterIndicators as $indicator)
                <x-filament::badge :color="$indicator->getColor()" role="listitem">
                    {{ $indicator->getLabel() }}

                    @if ($indicator->isRemovable())
                        <x-slot
                            name="deleteButton"
                            :label="__('filament-tables::table.filters.actions.remove.label')"
                            :wire:click="$indicator->getRemoveLivewireClickHandler()"
                            wire:target="removeTableFilter"
                        ></x-slot>
                    @endif
                </x-filament::badge>
            @endforeach
        </div>

        @if (collect($filterIndicators)->contains(fn ($indicator): bool => $indicator->isRemovable()))
            {{ $table->getFiltersRemoveAllAction() }}
        @endif
    </div>
@endif
