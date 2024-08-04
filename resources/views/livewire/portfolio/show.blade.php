<?php

use App\Models\Portfolio;
use Livewire\Attributes\{Title, Rule};
use Livewire\Volt\Component;

new class extends Component {

    public bool $showDrawer2 = false;
    public  $scope = 'YTD';

    public function changeScope ($scope)
    {
        $this->scope = $scope;
    }

    public function getScopeName ($scope)
    {
        return collect($this->options)->where('id', $scope)['name'];
    }

    public  $options =  [
        ['id' => '1M', 'name' => '1 month'],
        ['id' => '3M', 'name' => '3 months'],
        ['id' => 'YTD', 'name' => 'Year to date'],
        ['id' => '1Y', 'name' => '1 year'],
        ['id' => '3Y', 'name' => '3 years'],
        ['id' => 'ALL', 'name' => 'All time']
    ];

    public ?Portfolio $portfolio;

    public array $myChart = [
        'type' => 'line',
        'data' => [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
            'datasets' => [
                [
                    'label' => '# of Votes',
                    'data' => [2000, 2204, 3001, 2981, 2845],
                    'tension' => 0.4,
                    'pointStyle' => 'rect',
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6
                ],
                
            ]
        ],
    ];
}; ?>
  
<div x-data>

    <x-ib-drawer 
        key="manage-portfolio"
        title="{{ $portfolio->title }}"
    >

        <livewire:portfolio.manage-portfolio-form :portfolio="$portfolio" submit="update" hide-cancel />

    </x-ib-drawer>

    <x-ib-toolbar :title="$portfolio->title">

        <x-button 
            title="Edit Portfolio" 
            icon="o-pencil" 
            class="btn-circle btn-ghost btn-sm text-secondary" 
            @click="$dispatch('toggle-manage-portfolio')"
        />
    </x-ib-toolbar>

    <div class="grid mb-6 gap-5">

        <x-card class="bg-slate-100 dark:bg-base-200 rounded-lg ">

            

            <div class="flex justify-between items-center mb-2">
                    
                <div class="flex items-center">
                    
                    <h2 class="text-xl mr-4">Performance</h2>
                    <div id="chart-legend-portfolio-{{ $portfolio->id }}" class="flex space-around"></div>
                    
                </div>

                <x-dropdown label="{{ $scope }}" class="btn-ghost btn-sm" >
                        
                    <x-menu >
                        @foreach($options as $option)

                            <x-menu-item 
                                title="{{ $option['name'] }}" 
                                x-on:click="$wire.changeScope('{{ $option['id'] }}')"
                            />
                    
                        @endforeach
                    </x-menu>
                </x-dropdown>
                
            </div>
            
            {{-- <div
                class="h-[280px]"
                
            > --}}
                <x-ib-apex-chart :data="[]" name="portfolio-{{ $portfolio->id }}" />
            {{-- </div> --}}

        </x-card>

    </div>
    
    <div class="grid md:grid-cols-5 gap-5">
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Market Gain/Loss"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Total Cost Basis"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Total Market Value"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Realized Gain/Loss"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Dividends Earned"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        
    </div>

    <div class="mt-6 grid md:grid-cols-7 gap-5">

        <x-ib-card title="All portfolio holdings" class="md:col-span-4">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>

        <x-ib-card title="Top performers" class="md:col-span-3">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>
        
        <x-ib-card title="Top headlines" class="md:col-span-3">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>

        <x-ib-card title="Recent activity" class="md:col-span-4">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>

    </div>
    
</div>