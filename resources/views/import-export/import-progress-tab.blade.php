<?php

use App\Models\BackupImport as BackupImportModel;
use App\Traits\Toast;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;

    // props
    public ?BackupImportModel $backupImport = null;

    public function mount(): void
    {
        // the import page shows the import's progress itself
        if (request()->routeIs('import-export')) {
            return;
        }

        $this->backupImport = BackupImportModel::awaitingUser(auth()->id());
    }

    // methods
    public function checkImportStatus(): void
    {
        $this->backupImport?->refresh();

        if ($this->backupImport?->status == 'success') {

            $message = $this->backupImport->message;
            $this->backupImport = null;

            $this->success($message);
        }
    }
}; ?>

<div>
    @if($backupImport)
        <div @if(! $backupImport->isFinished()) wire:poll.2s.keep-alive="checkImportStatus" @endif>
            @if($backupImport->status == 'failed')
                <x-ui.progress-tab
                    href="{{ route('import-export') }}"
                    failed
                    title="{{ __('Import failed') }}"
                    message="{{ __('View problems') }}"
                    aria-label="{{ __('View import problems') }}"
                />
            @else
                <x-ui.progress-tab
                    href="{{ route('import-export') }}"
                    :message="$backupImport->message"
                    :progress="$backupImport->status == 'pending' ? null : $backupImport->progress"
                    aria-label="{{ __('Show import progress') }}"
                />
            @endif
        </div>
    @endif
</div>
