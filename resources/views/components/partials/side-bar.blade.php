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

        <div class="w-10 m-5"> <x-ui.logo /> </div>

        <x-ui.menu class="space-y-2" activate-by-route="true">
            <x-ui.menu-item title="{{ __('Dashboard') }}" link="{{ route('dashboard') }}" class="font-medium text-lg" />

            @foreach (auth()->user()->portfolios as $portfolio)
                <x-ui.menu-item 
                    :title="$portfolio->title" 
                    :badge="$portfolio->wishlist ? __('Wishlist') : null" 
                    badge-classes="badge-secondary badge-outline"
                    link="{{ route('portfolio.show', ['portfolio' => $portfolio->id ]) }}" 
                    class="font-medium text-md ms-1"
                />
            @endforeach
            
            <x-ui.menu-item title="{{ __('Create Portfolio') }}" link="{{ route('portfolio.create') }}" class="font-medium text-lg" />

            <x-ui.menu-item title="{{ __('Transactions') }}" link="{{ route('transaction.index') }}" class="font-medium text-lg" />
       
        </x-ui.menu>
        <div class="flex-1"></div>

        @php
            $user = auth()->user();
        @endphp

        <x-ui.list-item :item="$user" avatar="profile_photo_url" value="name" sub-value="email" no-separator no-hover class="rounded">
            <x-slot:actions>
                <x-ui.dropdown>
                    <x-slot:trigger>
                        <x-ui.button icon="o-cog-6-tooth" class="btn-circle btn-ghost btn-sm focus:rotate-180" />
                    </x-slot:trigger>
                    
                    <x-ui.menu-item title="{{ __('Manage Profile') }}" icon="o-user" link="{{ @route('profile.show') }}" />
                    <x-ui.menu-item title="{{ __('API Tokens') }}" icon="o-command-line" link="{{ @route('api-tokens.index') }}" />
                    <x-ui.menu-item title="{{ __('Import / Export Data') }}" icon="o-cloud-arrow-down" link="{{ @route('import-export') }}" />                                    

                    <x-ui.section-border class="py-1" />

                    <x-ui.menu-item title="{{ __('Log Out') }}" icon="o-power" onclick="event.preventDefault(); document.getElementById('logout').submit();" />
                    <form id="logout" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>

                </x-ui.dropdown>
                
            </x-slot:actions>
        </x-ui.list-item>
   </div>
</div>