<?php

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {

    use WithPagination;

    // props
    public User $user;
    public ?Transaction $editingTransaction;

    protected $listeners = [
        'transaction-updated' => '$refresh',
        'transaction-saved' => '$refresh'
    ];

    public array $sortBy = ['column' => 'date', 'direction' => 'desc'];

    public array $headers;

    // methods
    public function showTransactionDialog($transactionId)
    {
        $this->editingTransaction = Transaction::findOrFail($transactionId);
        $this->dispatch('toggle-manage-transaction');
    }

    public function mount()
    {
        $this->headers = [
            ['key' => 'date', 'label' => __('Date'), 'sortable' => true],
            ['key' => 'portfolio_title', 'label' => __('Portfolio')],
            ['key' => 'symbol', 'label' => __('Symbol'), 'class' => ''],
            ['key' => 'market_data_name', 'label' => __('Name')],
            ['key' => 'transaction_type', 'label' => __('Type')],
            ['key' => 'split', 'label' => __('Split')],
            ['key' => 'quantity', 'label' => __('Quantity')],
            ['key' => 'cost_basis', 'label' => __('Cost Basis')],
            ['key' => 'gain_dollars', 'label' => __('Gain/Loss')],
        ];
    }

    public function transactions()
    {
        return auth()
                    ->user()
                    ->transactions()
                    ->orderBy(...array_values($this->sortBy))
                    ->paginate(10);
    }

}; ?>

<div class="">
    
    <x-table 
        :headers="$headers" 
        :rows="$this->transactions()" 
        x-data="{ loadingId: null, timeout: null }"
        @row-click="
            timeout = setTimeout(() => { loadingId = $event.detail.id }, 200);
            $wire.showTransactionDialog($event.detail.id).then(() => {
                clearTimeout(timeout);
                loadingId = null;
            })
        "
        :sort-by="$sortBy" 
        with-pagination
    >
        @scope('cell_symbol', $row)
            <span class="flex">
                {{ $row->symbol }}
                <x-loading x-show="loadingId === '{{ $row->id }}'" x-cloak class="text-gray-400 ml-2" />
            </span>
        @endscope
        @scope('cell_date', $row)
            {{ $row->date->format('M d, Y') }}
        @endscope
        @scope('cell_split', $row)
            {{ $row->split ? 'Yes' : '' }}
        @endscope
        @scope('cell_transaction_type', $row)
            <x-badge 
                :value="$row->transaction_type" 
                class="{{ $row->transaction_type == 'BUY' 
                    ? 'badge-success' 
                    : 'badge-error' }} badge-sm mr-3" 
            />
        @endscope
        @scope('cell_cost_basis', $row)
            {{ Number::currency($row->cost_basis ?? 0) }}
        @endscope
        @scope('cell_total_cost_basis', $row)
            {{ Number::currency($row->total_cost_basis ?? 0) }}
        @endscope
        @scope('cell_gain_dollars', $row)
            {{ Number::currency($row->gain_dollars ?? 0) }}
        @endscope
        @scope('cell_market_data_market_value', $row)
            {{ Number::currency($row->market_data_market_value ?? 0) }}
        @endscope
        @scope('cell_total_market_value', $row)
            {{ Number::currency($row->total_market_value ?? 0) }}
        @endscope
    </x-table>

    <x-ib-modal 
        key="manage-transaction"
        title="Manage Transaction"
    >
        @livewire('manage-transaction-form', [
            'transaction' => $editingTransaction, 
        ], key($editingTransaction->id ?? 'new'))

    </x-ib-modal>
</div>