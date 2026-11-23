<?php

use App\Support\VersionCheck;
use Illuminate\Support\Collection;
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

    /**
     * Everything the bell should list for this user, newest concern first.
     *
     * @return Collection<int, array{icon: string, title: string, body: string, link: string}>
     */
    public function notifications(): Collection
    {
        return collect([
            $this->updateNotification(),
        ])->filter()->values();
    }

    /**
     * @return array{icon: string, title: string, body: string, link: string}|null
     */
    protected function updateNotification(): ?array
    {
        $versionCheck = app(VersionCheck::class);

        if (! $versionCheck->visibleTo(auth()->user()) || ! $versionCheck->updateAvailable()) {
            return null;
        }

        return [
            'icon' => 'o-arrow-up-circle',
            'title' => __('Update available'),
            'body' => __(':app :latest is now available.', [
                'app' => config('app.name'),
                'latest' => $versionCheck->latest(),
            ]).' '.trans_choice('{1} You are :count version behind.|[2,*] You are :count versions behind.', $versionCheck->versionsBehind()),
            'link' => VersionCheck::RELEASES_URL,
        ];
    }

}; ?>

<nav class="z-10 p-5 ml-0 md:ml-68 md:border-0 border-b border-zinc-200 dark:border-zinc-800">
    
    <div class="flex flex-wrap justify-between items-center">

        <div class="flex">
            <x-ui.button
                aria-controls="drawer-navigation"
                title="{{ __('Toggle Sidebar') }}"
                class="btn-circle btn-ghost btn-sm block md:hidden"
                icon="o-bars-3"
                @click="sideBarOpen = true"
            />

            <div class="ml-3 w-8 hidden sm:block md:hidden"> <x-ui.logo /> </div>
        </div>
    
        <div>
            <x-ui.button 
                @click.stop="$dispatch('toggle-spotlight')"
                class="btn-sm btn-ghost bg-base-300 flex-1 justify-start md:flex-none border-none"
            >
                <x-slot:label>
                    <span class="flex items-center text-gray-400">
                        <x-ui.icon name="o-magnifying-glass" class="mr-2" />
                        <span class=" truncate hidden sm:block">
                            @lang('Click or press :key to search', ['key' => '<kbd class="kbd kbd-sm text-gray-400" x-data x-text="/Mac|iPhone|iPad/.test(navigator.userAgent) ? \'⌘K\' : \'Ctrl K\'">Ctrl K</kbd>'])
                        </span>
                        <span class=" truncate block sm:hidden">
                            @lang('Click to search')
                        </span>
                    </span>
                </x-slot:label>
            </x-ui.button>

            <x-ui.spotlight
                search-text="{{ __('Search holdings, portfolios, or anything else...') }}"
                no-results-text="{{ __('Darn! Nothing found for that search.') }}"
            />
        </div>

        <div class="flex flex-0 items-center gap-4">

            @php
                $notifications = $this->notifications();
            @endphp

            <x-ui.dropdown id="notifications">
                <x-slot:trigger>
                    <x-ui.button
                        title="{{ __('Notifications') }}"
                        icon="o-bell"
                        class="btn-circle btn-ghost btn-sm relative"
                    >
                        @if ($notifications->isNotEmpty())
                            <span aria-label="{{ __('New notifications') }}" class="absolute top-1 right-1 w-2 h-2 rounded-full bg-warning"></span>
                        @endif
                    </x-ui.button>
                </x-slot:trigger>

                <div class="w-72 whitespace-normal">
                    <div class="px-3 py-2 font-semibold">{{ __('Notifications') }}</div>

                    @forelse ($notifications as $notification)
                        <li class="my-0.5">
                            <a
                                href="{{ $notification['link'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-start gap-3"
                            >
                                <x-ui.icon :name="$notification['icon']" class="w-5 h-5 mt-0.5 shrink-0 text-warning" />
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium">{{ $notification['title'] }}</span>
                                    <span class="block text-xs text-base-content/60">{{ $notification['body'] }}</span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <div class="px-3 py-6 text-center text-sm text-base-content/60">{{ __('No new notifications') }}</div>
                    @endforelse
                </div>
            </x-ui.dropdown>

            <x-ui.button 
                title="{{ __('Documentation') }}"
                icon="o-book-open"
                class="btn-circle btn-ghost btn-sm"
                link="https://investbra.in/docs"
                external
            >
            </x-ui.button>

            <x-ui.button 
                title="{{ __('We\'re open source!') }}"
                class="btn-circle btn-ghost btn-sm"
                link="https://github.com/investbrainapp/investbrain"
                external
            >
                <x-social.github-icon />
            </x-ui.button>

            <x-ui.theme-selector
                id="theme-selector"
                title="{{ __('Toggle Theme') }}" 
                class="btn-circle btn-ghost btn-sm" 
            />
        </div>
    </div>
</nav>


