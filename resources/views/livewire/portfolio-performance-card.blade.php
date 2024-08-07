<?php

use App\Models\Portfolio;
use Livewire\Attributes\{Title, Rule};
use Livewire\Volt\Component;

new class extends Component {

    // props
    public ?Portfolio $portfolio;
    public String $name = 'portfolio';
    public String $scope = 'YTD';
    public ?Int $value;
    public Array $options = [
        ['id' => '1M', 'name' => '1 month'],
        ['id' => '3M', 'name' => '3 months'],
        ['id' => 'YTD', 'name' => 'Year to date'],
        ['id' => '1Y', 'name' => '1 year'],
        ['id' => '3Y', 'name' => '3 years'],
        ['id' => 'ALL', 'name' => 'All time']
    ];

    protected $listeners = ['data-scope-updated' => 'test'];

    public function test()
    {
        $this->value = 148;
    }

    // methods
    public function mount() 
    {
        //
        
    }

}; ?>

<x-stat
    class="bg-slate-100 dark:bg-base-200"
    title="Market Gain/Loss"
    :value="$value"
    icon="o-arrow-trending-up"
/>
