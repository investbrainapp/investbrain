<?php

use App\Exports\BackupExport;
use App\Models\BackupImport as BackupImportModel;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;
    use WithFileUploads;

    // props
    #[Rule('required|extensions:xlsx|mimes:xlsx|max:2048')]
    public $file;

    public bool $importStatusDialog = false;

    public ?BackupImportModel $backupImport = null;

    public int $percent = 10;

    // methods
    public function import()
    {
        $this->validate();

        if (! RateLimiter::attempt('import:'.auth()->user()->id, $perMinute = 3, fn () => null)) {

            $this->error(__('Hang on! You\'re doing that too much.'));

            return;
        }

        $this->backupImport = BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => $this->file->getPathname(),
        ]);

        $this->importStatusDialog = true;

    }

    public function checkImportStatus()
    {
        if (Str::contains($this->backupImport?->message, 'portfolios')) {

            $this->percent = (1 / 2) * 100;
        }

        if (Str::contains($this->backupImport?->message, 'transactions')) {

            $this->percent = (3 / 4) * 100;
        }

        if (Str::contains($this->backupImport?->message, 'daily changes')) {

            $this->percent = (7 / 8) * 100;
        }

        if ($this->backupImport?->status == 'failed') {

            unset($this->file);
            $this->percent = 100;
        }

        if ($this->backupImport?->status == 'success') {

            $this->importStatusDialog = false;
            $this->backupImport = null;

            $this->success(__('Successfully imported!'), redirectTo: route('dashboard'));
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new BackupExport(empty: true), now()->format('Y_m_d').'_investbrain_template.xlsx');
    }
}; ?>

<x-forms.form-section submit="import">
    <x-slot name="title">
        {{ __('Import') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Upload or recover your Investbrain portfolio and holdings.') }} 
        <span class="text-xs text-secondary"><a href="#" title="{{ __('Click to download import template.') }}" @click="$wire.downloadTemplate()"> {{ __('Download import template.') }}</a></span>
    </x-slot>

    <x-slot:form>
        
        <div class="col-span-6 sm:col-span-4">
            <x-file wire:model="file" label="{{ __('Select a file') }}" hint="" accept=".xlsx" required />
        </div>

        <x-dialog-modal wire:model.live="importStatusDialog" persistent>
            <x-slot name="title">

                @if($backupImport?->status)
                <div 
                    class="{{ $backupImport?->status == 'failed' ? 'text-error' : '' }}"
                >
                    {{ $backupImport?->message }}
                </div>
                @endif
            </x-slot>
            <x-slot name="content">
                @if($backupImport?->status != 'failed')
                <x-ib-progress 
                    :indeterminate="$backupImport?->status == 'pending'"
                    class="progress-primary h-3"
                    value="{{ $percent }}"
                    max="100"
                />
                @endif
            </x-slot>
            
            <x-slot name="footer">
                @if($backupImport?->status == 'failed')

                    <x-ib-button wire:click="$toggle('importStatusDialog')"> {{ __('Try again') }} </x-ib-button>
                @else
                    <div wire:poll="checkImportStatus" class="text-gray-400 text-sm">{{ __('Your import will continue in the background') }}</div>
                    <x-ib-flex-spacer />
                    <x-ib-button wire:click="$toggle('importStatusDialog')"> {{ __('Close') }} </x-ib-button>
                @endif
            </x-slot>
        </x-dialog-modal>

    </x-slot:form>

    <x-slot name="actions">

        <x-forms.action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-forms.action-message>
  
        <x-ib-button type="submit" wire:loading.attr="disabled" spinner="import">
            {{ __('Import') }}
        </x-ib-button>
    </x-slot>
</x-forms.form-section>


