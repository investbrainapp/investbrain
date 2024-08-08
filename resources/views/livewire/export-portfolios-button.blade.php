<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    // props
    

    // methods
    public function mount() 
    {

        //
    }

    
}; ?>

<div>
    <x-button type="submit">
        {{ __('Download Export') }}
    </x-button>
</div>