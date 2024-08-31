<?php

use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Imports\BackupImport;
use App\Exports\BackupExport;
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

        try {

            $import = (new BackupImport)->import($this->file);

        } catch (\Throwable $e) {
     
            return $this->error($e->getMessage());
        }

        $this->success(__('Successfully imported!'), redirectTo: route('dashboard'));
    }

    public function downloadTemplate()
    {
        return Excel::download(new BackupExport(empty: true), now()->format('Y_m_d') . '_investbrain_template.xlsx');
    }
    
}; ?>

<x-forms.form-section submit="import">
    <x-slot name="title">
        {{ __('Import') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Upload or recover your Investbrain portfolio and holdings.') }} 
        <a href="#" title="{{ __('Click to download import template.') }}" @click="$wire.downloadTemplate()"> {{ __('Download import template.') }}</a>
    </x-slot>

    <x-slot name="form">
        
        <div class="col-span-6 sm:col-span-4">
            <x-file wire:model="file" label="{{ __('Select a file') }}" hint="" accept=".xlsx" required />
        </div>

    </x-slot>

    <x-slot name="actions">

        <x-forms.action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-forms.action-message>
  
        <x-button type="submit" spinner="import">
            {{ __('Import') }}
        </x-button>
    </x-slot>
</x-forms.form-section>


