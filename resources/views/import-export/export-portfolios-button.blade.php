<?php

use App\Actions\Export\StartBackupExport;
use App\Models\BackupExport as BackupExportModel;
use App\Traits\Toast;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;

    // props
    public ?BackupExportModel $backupExport = null;

    public function mount(): void
    {
        // pick back up an export that is still being built, or that is ready to download
        $this->backupExport = BackupExportModel::latestFor(auth()->id());
    }

    // methods
    public function export(StartBackupExport $startBackupExport): void
    {
        try {
            $this->backupExport = $startBackupExport->handle(auth()->user());
        } catch (ThrottleRequestsException $exception) {
            $this->error($exception->getMessage());
        }
    }

    public function checkExportStatus(): void
    {
        $this->backupExport?->refresh();
    }
}; ?>

<div>
    @if($backupExport && ! $backupExport->isFinished())

        <div wire:poll.2s.keep-alive="checkExportStatus" class="flex items-center gap-3 text-sm">
            <span class="loading loading-spinner loading-sm"></span>
            {{ __('Preparing your export. You can leave this page and come back when it\'s ready.') }}
        </div>

    @elseif($backupExport?->status == 'success')

        <div class="flex flex-wrap items-center gap-3">
            <a
                href="{{ route('import-export.download', $backupExport) }}"
                class="btn btn-primary !inline-flex lg:tooltip lg:tooltip-top"
                data-tip="{{ __('Created :time.', ['time' => $backupExport->completed_at->diffForHumans()]) }}"
            >
                <x-ui.icon name="o-arrow-down-tray" class="w-5 h-5" />
                {{ __('Download Export') }}
            </a>

            <x-ui.button class="btn-ghost" wire:click="export" spinner="export">
                {{ __('Generate new export') }}
            </x-ui.button>
        </div>

    @else

        @if($backupExport?->status == 'failed')
            <p class="mb-3 text-sm text-error">{{ __('Something went wrong while preparing your export.') }}</p>
        @endif

        <x-ui.button wire:click="export" spinner="export">
            {{ $backupExport?->status == 'failed' ? __('Try again') : __('Generate Export') }}
        </x-ui.button>

    @endif
</div>
