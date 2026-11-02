<?php

use App\Jobs\BackupExportJob;
use App\Models\BackupExport as BackupExportModel;
use App\Traits\Toast;
use Illuminate\Support\Facades\RateLimiter;
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
    public function export(): void
    {
        // e.g. a second tab or a double click while an export is being prepared
        $latestExport = BackupExportModel::latestFor(auth()->id());

        if ($latestExport && ! $latestExport->isFinished()) {
            $this->backupExport = $latestExport;

            return;
        }

        if (! RateLimiter::attempt('export:'.auth()->user()->id, $perMinute = 3, fn () => null)) {

            $this->error(__('Hang on! You\'re doing that too much.'));

            return;
        }

        // only the latest export is kept
        BackupExportModel::where('user_id', auth()->id())->get()->each->delete();

        $this->backupExport = BackupExportModel::create(['user_id' => auth()->id()]);

        BackupExportJob::dispatch($this->backupExport);

        $this->backupExport->refresh();
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
            <a href="{{ route('import-export.download', $backupExport) }}" class="btn btn-primary">
                <x-ui.icon name="o-arrow-down-tray" class="w-5 h-5" />
                {{ __('Download Export') }}
            </a>

            <x-ui.button class="btn-ghost" wire:click="export" spinner="export">
                {{ __('Generate new export') }}
            </x-ui.button>
        </div>

        <p class="mt-2 text-xs text-secondary-content">
            {{ __('Created :time.', ['time' => $backupExport->completed_at->diffForHumans()]) }}
        </p>

    @else

        @if($backupExport?->status == 'failed')
            <p class="mb-3 text-sm text-error">{{ __('Something went wrong while preparing your export.') }}</p>
        @endif

        <x-ui.button wire:click="export" spinner="export">
            {{ $backupExport?->status == 'failed' ? __('Try again') : __('Generate Export') }}
        </x-ui.button>

    @endif
</div>
