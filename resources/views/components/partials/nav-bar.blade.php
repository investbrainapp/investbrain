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

<nav class="z-10 p-5 ml-0 md:ml-64 md:border-0 border-b border-zinc-400 dark:border-zinc-800">
    
    <div class="flex flex-wrap justify-between items-center">

        <div class="flex">
            <x-ib-button
                aria-controls="drawer-navigation"
                title="{{ __('Toggle Sidebar') }}"
                class="btn-circle btn-ghost btn-sm block md:hidden"
                icon="o-bars-3"
                @click="sideBarOpen = true"
            />

            <div class="ml-3 w-8 hidden sm:block md:hidden"> <x-ib-logo /> </div>
        </div>
    
        <div>
            <x-ib-button 
                @click.stop="$dispatch('toggle-spotlight')"
                class="btn-sm bg-base-300 flex-1 justify-start md:flex-none"
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
                
            </x-ib-button>

            <x-ib-spotlight
                search-text="{{ __('Search holdings, portfolios, or anything else...') }}"
                no-results-text="{{ __('Darn! Nothing found for that search.') }}"
            />
        </div>

        <div class="flex flex-0 items-center gap-4">

            <x-ib-button 
                title="{{ __('Documentation') }}"
                icon="o-book-open"
                class="btn-circle btn-ghost btn-sm"
                link="https://github.com/investbrainapp/investbrain"
                external
            >
            </x-ib-button>

            <x-ib-button 
                title="{{ __('We\'re open source!') }}"
                class="btn-circle btn-ghost btn-sm"
                link="https://github.com/investbrainapp/investbrain"
                external
            >
                <x-github-icon />
            </x-ib-button>

            <x-ib-theme-selector
                title="{{ __('Toggle Theme') }}" 
                class="btn-circle btn-ghost btn-sm" 
            />
        </div>
    </div>
</nav>


