<?php

use App\Jobs\AssembleBackupExportJob;
use App\Jobs\CalculateDailyPerformanceJob;
use App\Models\BackupExport as BackupExportModel;
use App\Traits\Toast;
use Illuminate\Support\Facades\Bus;
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

        // the performance figures are slow to calculate, so each portfolio gets its own job
        // and the workbook is built once they have all finished
        $performanceJobs = auth()->user()->portfolios()->pluck('portfolios.id')
            ->map(fn ($portfolioId) => new CalculateDailyPerformanceJob($this->backupExport, $portfolioId));

        $jobs = [new AssembleBackupExportJob($this->backupExport)];

        if ($performanceJobs->isNotEmpty()) {
            array_unshift($jobs, Bus::batch($performanceJobs->all())->name('Backup export '.$this->backupExport->id));
        }

        Bus::chain($jobs)->dispatch();

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
