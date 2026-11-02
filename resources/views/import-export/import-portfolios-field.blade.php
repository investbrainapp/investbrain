<?php

use App\Exports\BackupExport;
use App\Models\BackupImport as BackupImportModel;
use App\Traits\Toast;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component
{
    use Toast;
    use WithFileUploads;

    // props
    #[Rule('required|extensions:xlsx|mimes:xlsx|max:2048')]
    public $file;

    public bool $importStatusDialog = false;

    public ?BackupImportModel $backupImport = null;

    public function mount(): void
    {
        // pick back up an import that is still running (e.g. after navigating away)
        $this->backupImport = BackupImportModel::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('created_at', '>=', now()->subHour())
            ->latest()
            ->first();

        $this->importStatusDialog = ! is_null($this->backupImport);
    }

    // methods
    public function import()
    {
        $this->validate();

        if (! RateLimiter::attempt('import:'.auth()->user()->id, $perMinute = 3, fn () => null)) {

            $this->error(__('Hang on! You\'re doing that too much.'));

            return;
        }

        $this->importStatusDialog = true;

        $this->backupImport = BackupImportModel::create([
            'user_id' => auth()->user()->id,
            'path' => $this->file->getPathname(),
        ]);

        $this->checkImportStatus();
    }

    public function checkImportStatus()
    {
        $this->backupImport?->refresh();

        if ($this->backupImport?->status == 'failed') {

            $this->reset('file');
        }

        if ($this->backupImport?->status == 'success') {

            $this->importStatusDialog = false;
            $message = $this->backupImport->message;
            $this->backupImport = null;

            $this->success($message, redirectTo: route('dashboard'));
        }
    }

    public function closeImportStatus()
    {
        $this->importStatusDialog = false;

        if ($this->backupImport?->isFinished()) {
            $this->backupImport = null;
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
        <span class="text-xs text-secondary"><a href="#" title="{{ __('Click to download import template.') }}" @click.prevent="$wire.downloadTemplate()"> {{ __('Download import template.') }}</a></span>
    </x-slot>

    <x-slot:form>

        <div class="col-span-6 sm:col-span-4">
            <x-ui.file wire:model="file" label="{{ __('Select a file') }}" hint="{{ __('An .xlsx file in the import template format (an Investbrain export works too).') }}" accept=".xlsx" required />
        </div>


        <x-ui.dialog-modal wire:model.live="importStatusDialog" box-class="max-w-3xl" persistent>
            <x-slot name="title">
                @if($backupImport?->status == 'failed')
                    <div class="text-error">{{ $backupImport->message }}</div>
                @else
                    {{ __('Importing your file') }}
                @endif
            </x-slot>

            <x-slot name="content">
                @if($backupImport?->status == 'failed')

                    <p class="mb-3">{{ __('Fix these problems in your spreadsheet and upload it again.') }}</p>

                    <div class="overflow-auto max-h-96 border border-base-300 rounded-md">
                        <table class="table table-sm table-pin-rows">
                            <thead>
                                <tr>
                                    <th>{{ __('Sheet') }}</th>
                                    <th>{{ __('Row') }}</th>
                                    <th>{{ __('Column') }}</th>
                                    <th>{{ __('Problem') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($backupImport->errors ?? [] as $error)
                                <tr wire:key="import-error-{{ $loop->index }}">
                                    <td class="whitespace-nowrap align-top">{{ $error['sheet'] ?? '' }}</td>
                                    <td class="align-top">{{ $error['row'] ?? '' }}</td>
                                    <td class="whitespace-nowrap align-top">{{ $error['column'] ?? '' }}</td>
                                    <td class="align-top">
                                        {{ $error['message'] }}
                                        @if(filled($error['value'] ?? null))
                                            <span class="block text-xs text-secondary-content font-mono">{{ __('Value: :value', ['value' => $error['value']]) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($backupImport->error_count > count($backupImport->errors ?? []))
                        <p class="mt-2 text-xs text-secondary-content">
                            {{ __('Showing the first :shown of :total problems.', ['shown' => count($backupImport->errors ?? []), 'total' => $backupImport->error_count]) }}
                        </p>
                    @endif

                @else

                    <div class="mb-2">{{ $backupImport?->message }}</div>

                    <x-ui.progress
                        :indeterminate="in_array($backupImport?->status, [null, 'pending'])"
                        class="progress-primary h-3"
                        value="{{ $backupImport?->progress ?? 0 }}"
                        max="100"
                    />
                @endif
            </x-slot>

            <x-slot name="footer">
                @if($backupImport?->status == 'failed')

                    <x-ui.button wire:click="closeImportStatus"> {{ __('Try again') }} </x-ui.button>
                @else
                    <div @if($backupImport && ! $backupImport->isFinished()) wire:poll.2s="checkImportStatus" @endif class="text-gray-400 text-sm">{{ __('Your import will continue in the background') }}</div>
                    <x-ui.flex-spacer />
                    <x-ui.button wire:click="closeImportStatus"> {{ __('Dismiss') }} </x-ui.button>
                @endif
            </x-slot>
        </x-ui.dialog-modal>

    </x-slot:form>

    <x-slot name="actions">

        <x-forms.action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-forms.action-message>

        <x-ui.button type="submit" wire:loading.attr="disabled" spinner="import">
            {{ __('Import') }}
        </x-ui.button>
    </x-slot>
</x-forms.form-section>
