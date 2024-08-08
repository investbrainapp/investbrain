<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    // props
    public $file;

    // methods
    public function mount() 
    {

        //
    }
    
}; ?>

<x-file wire:model="file" label="Select a file" hint="" accept="application/pdf" required />
