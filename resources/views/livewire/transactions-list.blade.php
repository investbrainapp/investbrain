<?php

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component {

    // props
    public Portfolio $portfolio;
    public ?Transaction $editingTransaction;

    // methods
    public function showTransactionDialog($transactionId)
    {
        $this->editingTransaction = Transaction::findOrFail($transactionId);
        $this->dispatch('toggle-manage-transaction');
    }

}; ?>

<div class="">

    @foreach($portfolio->transactions->take(10) as $transaction)

        <x-list-item 
            no-separator 
            :item="$transaction" 
            class="cursor-pointer"
            x-data="{ loading: false, timeout: null }"
            @click="
                timeout = setTimeout(() => { loading = true }, 200);
                $wire.showTransactionDialog('{{ $transaction->id }}').then(() => {
                    clearTimeout(timeout);
                    loading = false;
                })
            "
        >
            <x-slot:value class="flex items-center">
                <x-badge 
                    :value="$transaction->transaction_type" 
                    class="{{ $transaction->transaction_type == 'BUY' 
                        ? 'badge-success' 
                        : 'badge-error' }} badge-sm mr-3" 
                />
                {{ $transaction->date->format('M j, Y') }} 
                {{ $transaction->symbol }} 
                ({{ $transaction->quantity }} 
                @ {{ $transaction->transaction_type == 'BUY' 
                    ? Number::currency($transaction->cost_basis)
                    : Number::currency($transaction->sale_price) }})

                <x-loading x-show="loading" x-cloak class="text-gray-400 ml-2" />
            </x-slot:value>
        </x-list-item>

    @endforeach

    <x-ib-modal 
        key="manage-transaction"
        title="Manage Transaction"
    >
        @livewire('manage-transaction-form', [
            'portfolio' => $portfolio, 
            'transaction' => $editingTransaction, 
        ], key($editingTransaction->id ?? 'new'))

    </x-ib-modal>
</div>