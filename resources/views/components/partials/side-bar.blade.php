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

<div 
    aria-label="Sidebar"
    style="background-image: url('{{ asset('images/noise.svg') }}')"
    class="
        h-full
        bg-base-300
        border-r
        border-zinc-200
        dark:border-zinc-800
        fixed
        top-0
        left-0
        z-50
        w-64
        transition-transform
        -translate-x-full
        md:translate-x-0
    " 
    :class="{'translate-x-0': sideBarOpen, '-translate-x-full': !sideBarOpen}"
    x-data="{
        responsiveSidebar() {
            if (window.innerWidth >= 768) {
                this.sideBarOpen = true
                return;
            }
            this.sideBarOpen = false
        }
    }"
    @resize.window="responsiveSidebar"
    @keyup.escape.window="sideBarOpen = false"
>
    <template x-teleport="body">
        <div
            aria-label="Overlay"
            class="block md:hidden z-10 fixed w-screen h-screen inset-0 bg-black/20 backdrop-blur-sm"
            x-on:click="sideBarOpen=false"
            x-show="sideBarOpen"
            x-cloak
        ></div>
    </template>
    
    <div class="h-full px-1 overflow-y-auto flex flex-col">

        <div class="w-10 m-5"> <x-ib-logo /> </div>

        <ul class="space-y-2 font-medium">
            <li>
                <a wire:navigate title="title="{{ __('Dashboard') }}" href="{{ route('dashboard') }}" class="flex items-center p-2 rounded-sm hover:bg-gray-100 dark:hover:bg-gray-700 group">
                
                <span class="ms-3"> {{ __('Dashboard') }} </span>
                </a>
            </li>

            @foreach (auth()->user()->portfolios as $portfolio)
            <li>
                <a wire:navigate title="title="{{ __('Portfolios') }}" href="{{ route('portfolio.show', ['portfolio' => $portfolio->id ]) }}" class="flex items-center p-2 rounded-sm hover:bg-gray-100 dark:hover:bg-gray-700 group">
                
                <span class="ms-3"> {{ $portfolio->title }}  
                    @if($portfolio->wishlist)
                        <x-badge value="{{ __('Wishlist') }}" class="badge-secondary badge-sm ml-2" />
                    @endif
                </span>
                </a>
            </li>
            @endforeach

            <li>
                <a wire:navigate title="title="{{ __('Dashboard') }}"" href="{{ route('dashboard') }}" class="flex items-center p-2 text-gray-900 rounded-sm dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                
                <span class="ms-3"> {{ __('Dashboard') }} </span>
                </a>
            </li>
            
            <li>
                <a wire:navigate title="title="{{ __('Create Portfolio') }}"" href="{{ route('portfolio.create') }}" class="flex items-center p-2 text-gray-900 rounded-sm dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                
                <span class="ms-3"> {{ __('Create Portfolio') }} </span>
                </a>
            </li>
            <li>
                <a wire:navigate title="title="{{ __('Transactions') }}"" href="{{ route('transaction.index') }}" class="flex items-center p-2 text-gray-900 rounded-sm dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                
                <span class="ms-3"> {{ __('Transactions') }} </span>
                </a>
            </li>
        </ul>
        <div class="flex-1"></div>

        @php
            $user = auth()->user();
        @endphp

        <x-list-item :item="$user" avatar="profile_photo_url" value="name" sub-value="email" no-separator no-hover class="rounded">
            <x-slot:actions>
                <x-dropdown>
                    <x-slot:trigger>
                        <x-ib-button icon="o-cog-6-tooth" class="btn-circle btn-ghost btn-xs" />
                    </x-slot:trigger>
                    
                    <x-menu-item title="{{ __('Manage Profile') }}" icon="o-user" link="{{ @route('profile.show') }}" />
                    <x-menu-item title="{{ __('API Tokens') }}" icon="o-command-line" link="{{ @route('api-tokens.index') }}" />
                    <x-menu-item title="{{ __('Import / Export Data') }}" icon="o-cloud-arrow-down" link="{{ @route('import-export') }}" />                                    

                    <x-ib-section-border class="py-1" />

                    <x-menu-item title="{{ __('Log Out') }}" icon="o-power" onclick="event.preventDefault(); document.getElementById('logout').submit();" />
                    <form id="logout" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>

                </x-dropdown>
                
            </x-slot:actions>
        </x-list-item>
   </div>
</div>