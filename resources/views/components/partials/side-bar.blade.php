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

<div class="
    flex
    flex-col
    !transition-all
    !duration-100
    ease-out
    overflow-x-hidden
    overflow-y-auto
    h-screen
    lg:h-[calc(100vh-73px)]
    bg-base-100
    lg:bg-inherit
    {{ session('mary-sidebar-collapsed') == 'true' ? 'w-[70px] [&>*_summary::after]:hidden [&_.mary-hideable]:hidden [&_.display-when-collapsed]:block [&_.hidden-when-collapsed]:hidden' : null }}
    {{ session('mary-sidebar-collapsed') != 'true' ? 'w-[270px] [&>*_summary::after]:block [&_.mary-hideable]:block [&_.hidden-when-collapsed]:block [&_.display-when-collapsed]:hidden' : null }}
">
    <div class="flex-1">
        <x-menu activate-by-route>

            <x-menu-item title="{{ __('Dashboard') }}" icon="o-home" link="{{ route('dashboard') }}" />
            <x-menu-sub title="{{ __('Portfolios') }}" icon="o-document-duplicate">
                @foreach (auth()->user()->portfolios as $portfolio)
                <x-menu-item  icon="o-document" link="{{ route('portfolio.show', ['portfolio' => $portfolio->id ]) }}" >
                    <x-slot:title> 
                        {{ $portfolio->title }} 
                        @if($portfolio->wishlist)
                        <x-badge value="{{ __('Wishlist') }}" class="badge-secondary badge-sm ml-2" />
                        @endif
                    </x-slot:title>
                    
                </x-menu-item>
                @endforeach

                <x-menu-item title="{{ __('Create Portfolio') }}" icon="o-document-plus" link="{{ route('portfolio.create') }}" />
            </x-menu-sub>
            <x-menu-item title="{{ __('Transactions') }}" icon="o-banknotes" link="{{ route('transaction.index') }}" />
            {{-- <x-menu-item title="{{ __('Reporting') }}" icon="o-chart-bar-square" link="####" /> --}}

        </x-menu>

    </div>

    <div class="px-3">

        <x-section-border />

        @php
            $user = auth()->user();
        @endphp

        <x-list-item :item="$user" avatar="profile_photo_url" value="name" sub-value="email" no-separator no-hover class="mb-3 !-mt-3 rounded">
            <x-slot:actions>
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button icon="o-cog-6-tooth" class="btn-circle btn-ghost btn-xs" />
                    </x-slot:trigger>
                    
                    <x-menu-item title="{{ __('Manage Profile') }}" icon="o-user" link="{{ @route('profile.show') }}" />
                    <x-menu-item title="{{ __('API Tokens') }}" icon="o-command-line" link="{{ @route('api-tokens.index') }}" />
                    <x-menu-item title="{{ __('Import / Export Data') }}" icon="o-cloud-arrow-down" link="{{ @route('import-export') }}" />                                    

                    <x-section-border class="py-1" />

                    <x-menu-item title="{{ __('Log Out') }}" icon="o-power" onclick="event.preventDefault(); document.getElementById('logout').submit();" />
                    <form id="logout" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>

                </x-dropdown>
                
            </x-slot:actions>
        </x-list-item>
    </div>
</div>