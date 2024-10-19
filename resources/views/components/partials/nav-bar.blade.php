
<div class="bg-base-100 border-base-300 border-b sticky top-0 z-10">
    <div class="flex justify-between items-center px-7 py-3 mx-auto">
        <div class="flex-1 flex items-center">
            
            <label for="main-drawer" class="lg:hidden mr-3">
                <x-icon name="o-bars-3" class="cursor-pointer" />
            </label>

            <div class="hidden md:block" style="height:3.1em">
                <x-application-logo  />
            </div>

        </div>
        <div class="flex flex-grow gap-4 w-50" x-data>

            <x-spotlight
                shortcut="slash"
                search-text="{{ __('Search holdings, portfolios, or anything else...') }}"
                no-results-text="{{ __('Darn! Nothing found for that search.') }}"
            />
            
            <x-button 
                @click.stop="$dispatch('mary-search-open')"
                class="btn-sm w-32 justify-start md:w-auto"
            >
                <x-slot:label>
                    <span class="flex">
                        <x-icon name="o-magnifying-glass" class="mr-2" />
                        <span class="hidden md:block">
                            @lang('Click or press :key to search', ['key' => '<kbd class="kbd kbd-sm">/</kbd>'])
                        </span>
                    </span>
                </x-slot:label>
            </x-button>

        </div>
        <div class="flex items-center gap-4">

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