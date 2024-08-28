<?php

use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Imports\BackupImport;
use Livewire\Attributes\Rule;

new class extends Component {
    use Toast;
    use WithFileUploads;

    // props
    #[Rule('required|file|mimes:xlsx|max:2048')]
    public $file;

    // methods
    public function import() 
    {

        $this->validate();

        $import = (new BackupImport)->import($this->file);

        $this->success(__('Successfully imported!'));

        // Artisan::queue(RefreshHoldingData::class);
    }
    
}; ?>

<x-forms.form-section submit="import">
    <x-slot name="title">
        {{ __('Import') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Upload or recover your Investbrain portfolio and holdings.') }}
    </x-slot>

    <x-slot name="form">
        
        <div class="col-span-6 sm:col-span-4">
            <x-file wire:model="file" label="{{ __('Select a file') }}" hint="" accept=".xlsx" required />
        </div>

    </x-slot>

    <x-slot name="actions">
  
        <x-button type="submit">
            {{ __('Import') }}
        </x-button>
    </x-slot>
</x-forms.form-section>


