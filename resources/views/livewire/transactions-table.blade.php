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
            ['key' => 'quantity', 'label' => __('Quantity')],
            ['key' => 'cost_basis', 'label' => __('Cost Basis')],
            ['key' => 'total_cost_basis', 'label' => __('Total Cost Basis')],
            ['key' => 'market_data_market_value', 'label' => __('Market Value')],
            ['key' => 'total_market_value', 'label' => __('Total Market Value')],
            // ['key' => 'market_gain_dollars', 'label' => __('Market Gain/Loss')],
            // ['key' => 'market_gain_percent', 'label' => __('Market Gain/Loss')],
            // ['key' => 'realized_gain_dollars', 'label' => __('Realized Gain/Loss')],
            // ['key' => 'dividends_earned', 'label' => __('Dividends Earned')],
            // ['key' => 'market_data_updated_at', 'label' => __('Market Data Age')],
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
        @scope('cell_realized_gain_dollars', $row)
            {{ Number::currency($row->realized_gain_dollars ?? 0) }}
        @endscope
        @scope('cell_market_gain_dollars', $row)
            {{ Number::currency($row->market_gain_dollars ?? 0) }}
        @endscope
        @scope('cell_market_gain_percent', $row)
            {{ Number::percentage($row->market_gain_percent ?? 0) }}
        @endscope
        @scope('cell_market_data_market_value', $row)
            {{ Number::currency($row->market_data_market_value ?? 0) }}
        @endscope
        @scope('cell_total_market_value', $row)
            {{ Number::currency($row->total_market_value ?? 0) }}
        @endscope
        @scope('cell_dividends_earned', $row)
            {{ Number::currency($row->dividends_earned ?? 0) }}
        @endscope
        @scope('cell_market_data_updated_at', $row)
            {{ \Carbon\Carbon::parse($row->market_data_updated_at)->diffForHumans() }}
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