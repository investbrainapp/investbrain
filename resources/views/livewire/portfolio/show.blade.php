<?php

use App\Models\Portfolio;
use Livewire\Attributes\{Title, Rule};
use Livewire\Volt\Component;

new class extends Component {

    public bool $showDrawer2 = false;

    public ?Portfolio $portfolio;
    
}; ?>
  
<div>
    

    <div class="p-8 bg-base-200 rounded-md ">
        <div class="flex justify-between mb-8">
            <h1 class="text-2xl font-medium">{{ $portfolio->title }} </h1>
            <x-button 
                title="Edit Portfolio" 
                icon="o-cog-8-tooth" 
                class="btn-circle btn-ghost btn-sm" 
                wire:click="$toggle('showDrawer2')" 
            />
        </div>

        <div class="grid md:grid-cols-4 gap-5">
            <x-stat
                title="Realized Gain/Loss ($)"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
            <x-stat
                title="Realized Gain/Loss (%)"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
            <x-stat
                title="Market Gain/Loss ($)"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
            <x-stat
                title="Market Gain/Loss (%)"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
            <x-stat
                title="Total Cost Basis ($)"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
            <x-stat
                title="Total Market Value ($)"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
            <x-stat
                title="Number of Transactions"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
            <x-stat
                title="Dividends Earned ($)"
                description="This month"
                value="22.124"
                icon="o-arrow-trending-up"
                tooltip-bottom="There" 
            />
        </div>
        <div class="grid md:grid-cols-3 gap-5">
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item :item="$user" link="/docs/installation" />
            @endforeach
        </div>
    </div>
    
    <x-drawer 
        title="{{ $portfolio->title }}"
        wire:model="showDrawer2" 
        class="w-11/12 lg:w-1/3" 
        with-close-button
        close-on-escape
        right
    >
        <livewire:portfolio.manage-portfolio-form :portfolio="$portfolio" submit="update" hide-cancel />

    </x-drawer>
    
</div>