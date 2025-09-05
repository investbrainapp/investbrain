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

<nav class="z-10 p-5 ml-0 md:ml-64 md:border-0 border-b border-zinc-200 dark:border-zinc-800">
    
    <div class="flex flex-wrap justify-between items-center">

        <div class="flex">
            <x-button
                aria-controls="drawer-navigation"
                title="{{ __('Toggle Sidebar') }}"
                class="btn-circle btn-ghost btn-sm block md:hidden"
                icon="o-bars-3"
                @click="sideBarOpen = true"
            />

            <div class="ml-3 w-8 hidden sm:block md:hidden"> <x-ib-logo /> </div>
        </div>
    
        <div>
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
                darkTheme="dark" 
                lightTheme="light"
            />
        </div>
    </div>
</nav>


