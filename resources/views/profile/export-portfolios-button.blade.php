<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BackupExport;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component {
    use Toast;

    // props
    
    // methods
    public function export() 
    {        
        if (!RateLimiter::attempt('export:'.auth()->user()->id, $perMinute = 3, fn()=>null)) {

            $this->error(__('Hang on! You\'re doing that too much.'));
            return;
        }

        return Excel::download(new BackupExport, now()->format('Y_m_d') . '_investbrain_backup.xlsx');
    }

}; ?>

<div>
    <x-button type="submit" @click="$wire.export" spinner="export">
        {{ __('Download Export') }}
    </x-button>
</div>