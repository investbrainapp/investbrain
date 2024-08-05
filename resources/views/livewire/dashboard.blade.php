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
        ['id' => '2M', 'name' => '3 months'],
        ['id' => 'YTD', 'name' => 'Year to date'],
        ['id' => '1Y', 'name' => '1 year'],
        ['id' => '3Y', 'name' => '3 years'],
        ['id' => 'ALL', 'name' => 'All time']
    ];

    public ?Portfolio $portfolio;


    private function generateDateSeries($startDate, $endDate) {
        $dateArray = [];
        $currentDate = strtotime($startDate);
        $endDate = strtotime($endDate);

        while ($currentDate <= $endDate) {
            // Generate a random integer
            $randomInt = rand(1000, 3000);

            // Format the current date to 'Y-m-d'
            $formattedDate = date('Y-m-d', $currentDate);

            // Append the date and random integer to the array
            $dateArray[] = [$formattedDate, $randomInt];

            // Move to the next day
            $currentDate = strtotime("+1 day", $currentDate);
        }

        return $dateArray;
    }

    public array $myChart;

    public function mount() {

        $this->myChart = [
            'series' => [
                [
                    'name' => 'Total Views',
                    'data' => $this->generateDateSeries('2024-01-01', '2024-08-01')
                ],      
                [
                    'name' => 'Second Views',
                    'data' => $this->generateDateSeries('2024-01-01', '2024-08-01')
                ],    
            ],

        ];

    }

    
}; ?>
  
<div x-data>


    <x-card class="bg-slate-100 dark:bg-base-200 rounded-lg mb-6">


        <div class="flex justify-between items-center mb-2">
                
            <div class="flex items-center">
                
                <h2 class="text-xl mr-4">Performance</h2>
                <div id="chart-legend-dashboard" class="flex space-around"></div>
                
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
        
        <div
            class="h-[280px] mb-5"
        >
            <x-ib-apex-chart :key="Str::uuid()" :series-data="$myChart" name="dashboard"  />
        </div>


    </x-card>

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

        <x-ib-card title="My portfolios" class="md:col-span-4">
         
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