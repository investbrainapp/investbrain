<?php

use Livewire\WithFileUploads;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use App\Imports\BackupImport;
use App\Exports\BackupExport;
use Livewire\Attributes\Rule;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    use Toast;
    use WithFileUploads;

    // props
    #[Rule('required|extensions:xlsx|mimes:xlsx|max:2048')]
    public $file;

    // methods
    public function import() 
    {
        $this->validate();

        if (!RateLimiter::attempt('import:'.auth()->user()->id, $perMinute = 3, fn()=>null)) {

            $this->error(__('Hang on! You\'re doing that too much.'));
            return;
        }

        try {

            $import = Excel::import(
                new BackupImport, 
                $this->file->getPathname(), 
                config('livewire.temporary_file_upload.disk', null)
            );

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
        <strong><a href="#" title="{{ __('Click to download import template.') }}" @click="$wire.downloadTemplate()"> {{ __('Download import template.') }}</a></strong>
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
  
        <x-button type="submit" wire:loading.attr="disabled" spinner="import">
            {{ __('Import') }}
        </x-button>
    </x-slot>
</x-forms.form-section>


