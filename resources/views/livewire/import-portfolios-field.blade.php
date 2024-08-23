<?php

use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;
    use WithFileUploads;

    // props
    public $file;

    // methods
    public function mount() 
    {

        //
    }
    
}; ?>

<x-file wire:model="file" label="{{ __('Select a file') }}" hint="" accept="application/pdf" required />
