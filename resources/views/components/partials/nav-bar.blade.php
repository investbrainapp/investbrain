<?php

use Livewire\Volt\Component;

new class extends Component
{
    // props

    /**
     * The component's listeners.
     *
     * @var array
     */
    protected $listeners = [
        'refresh-navigation-menu' => '$refresh',
    ];

    // methods

}; ?>
<div class="bg-base-100 border-base-300 border-b sticky top-0 z-10">
    <div class="flex justify-between items-center px-7 py-3 gap-4 mx-auto">
        <div class="flex flex-0 items-center">
            
            <label for="main-drawer" class="lg:hidden mr-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>

            <div class="hidden md:block" style="height:2.5em">
                <x-application-logo  />
            </div>

        </div>
        <div class="flex flex-1 justify-center" x-data>

            <x-spotlight
                shortcut="slash"
                search-text="{{ __('Search holdings, portfolios, or anything else...') }}"
                no-results-text="{{ __('Darn! Nothing found for that search.') }}"
            />
            
            <x-button 
                @click.stop="$dispatch('mary-search-open')"
                class="btn-sm flex-1 justify-start md:flex-none"
            >
                <x-slot:label>
                    <span class="flex items-center text-gray-400">
                        <x-icon name="o-magnifying-glass" class="mr-2" />
                        <span class=" truncate hidden sm:block">
                            @lang('Click or press :key to search', ['key' => '<kbd class="kbd kbd-sm">/</kbd>'])
                        </span>
                        <span class=" truncate block sm:hidden">
                            @lang('Click to search')
                        </span>
                    </span>
                </x-slot:label>
            </x-button>

        </div>
        <div class="flex flex-0 items-center gap-4">

            <x-button 
                title="{{ __('Documentation') }}"
                icon="o-book-open"
                class="btn-circle btn-ghost btn-sm"
                link="https://github.com/investbrainapp/investbrain"
                external
            >
            </x-button>

            <x-button 
                title="{{ __('We\'re open source!') }}"
                class="btn-circle btn-ghost btn-sm"
                link="https://github.com/investbrainapp/investbrain"
                external
            >
                <x-github-icon />
            </x-button>

            <x-theme-toggle 
                title="{{ __('Toggle Theme') }}" 
                class="btn-circle btn-ghost btn-sm" 
                darkTheme="business" 
                lightTheme="corporate"
            />
        </div>
    </div>
</div>