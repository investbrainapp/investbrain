<?php

use App\Models\Portfolio;
use Livewire\Attributes\{Title, Rule};
use Livewire\Volt\Component;

new class extends Component {

    // props
    public ?Portfolio $portfolio;
    public String $name = 'portfolio';
    public String $scope = 'YTD';
    public Array $options = [
        ['id' => '1M', 'name' => '1 month'],
        ['id' => '3M', 'name' => '3 months'],
        ['id' => 'YTD', 'name' => 'Year to date'],
        ['id' => '1Y', 'name' => '1 year'],
        ['id' => '3Y', 'name' => '3 years'],
        ['id' => 'ALL', 'name' => 'All time']
    ];

    // data
    public Array $myChart;

    // methods
    public function mount() 
    {
        $this->myChart = [
            'series' => [
                [
                    'name' => __('Total Views'),
                    'data' => $this->generateDateSeries('2024-01-01', '2024-08-01')
                ],      
                [
                    'name' => __('Second Views'),
                    'data' => $this->generateDateSeries('2024-01-01', '2024-08-01')
                ],    
            ],

        ];
    }

    public function changeScope($scope)
    {
        $this->scope = $scope;

        $this->dispatch('data-scope-updated', $scope);
    }

    public function getScopeName($scope)
    {
        return collect($this->options)->where('id', $scope)['name'];
    }

    private function generateDateSeries($startDate, $endDate) 
    {
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

}; ?>

<div>
    <div class="flex justify-between items-center mb-2">
                    
        <div class="flex items-center">
            
            <h2 class="text-xl mr-4">{{ __('Performance') }}</h2>
            <div id="chart-legend-{{ $name }}" class="flex space-between"></div>
            
        </div>

        <x-dropdown label="{{ $scope }}" class="btn-ghost btn-sm">
                
            <x-menu>
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
        <x-ib-apex-chart :series-data="$myChart" :name="$name" />
    </div>

</div>
