<?php

use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // props
    public Collection $transactions;

    public ?Portfolio $portfolio;

    public ?Transaction $editingTransaction;

    public bool $shouldGoToHolding = true;

    public bool $showPortfolio = false;

    public bool $paginate = true;

    public int $perPage = 5;

    public int $offset = 0;

    protected $listeners = [
        'transaction-updated' => '$refresh',
        'transaction-saved' => '$refresh',
    ];

    // methods
    public function showTransactionDialog($transactionId)
    {
        if (! auth()->user()->can('fullAccess', $this->portfolio)) {
            $this->error(__('You do not have permission to manage transactions for this portfolio'));

            return;
        }

        $this->editingTransaction = Transaction::findOrFail($transactionId);
        $this->dispatch('toggle-manage-transaction');
    }

    public function goToHolding($holding)
    {
        return $this->redirect(route('holding.show', ['portfolio' => $holding['portfolio_id'], 'symbol' => $holding['symbol']]));
    }

    public function updateOffset($amount = 0)
    {
        $this->offset = $this->offset + $amount;
    }
}; ?>

<div class="">

    @foreach($transactions->sortByDesc('date')->slice($offset)->take($perPage) as $transaction)

        <x-ib-list-item 
            no-separator 
            :item="$transaction" 
            class="cursor-pointer"
            x-data="{ loading: false, timeout: null }"
            :key="$transaction->id"
            @click="
                if ($wire.shouldGoToHolding) {

                    $wire.goToHolding({{ $transaction }})
                    
                    return;
                }
                timeout = setTimeout(() => { loading = true }, 200);
                $wire.showTransactionDialog('{{ $transaction->id }}').then(() => {
                    clearTimeout(timeout);
                    loading = false;
                })
            "
        >
            <x-slot:value class="flex items-center">
                <x-badge 
                    :value="$transaction->split
                        ? 'SPLIT'
                        : ($transaction->reinvested_dividend
                            ? 'REINVEST' 
                            : $transaction->transaction_type)" 
                    class="{{ $transaction->transaction_type == 'BUY' 
                        ? 'badge-success' 
                        : 'badge-error' }} badge-sm mr-3" 
                />
                {{ $transaction->symbol }} 
                ({{ $transaction->quantity }} 
                @ {{ Number::currency(
                    $transaction->transaction_type == 'BUY' 
                        ? $transaction->cost_basis
                        : $transaction->sale_price,
                    $transaction->market_data?->currency
                ) }})

                <x-ib-loading x-show="loading" x-cloak class="text-gray-400 ml-2" />
            </x-slot:value>
            <x-slot:sub-value>
                @if($showPortfolio)
                <span title="{{ __('Portfolio') }}">{{ $transaction->portfolio->title }} </span>
                &middot;
                @endif
                <span title="{{ __('Transaction Date') }}">{{ $transaction->date->format('F j, Y') }} </span>
            </x-slot:sub-value>
        </x-ib-list-item>

    @endforeach

    @if ($paginate && count($transactions) > $perPage)
        <div class="flex justify-between">
            
            <span>
                @if($offset > 0)
                <x-ib-button 
                    class="btn btn-sm btn-ghost text-secondary"
                    wire:click="updateOffset(-{{ $perPage }})"
                >
                    {!! __('pagination.previous') !!}
                </x-ib-button>
                @endif
            </span>

            <span>
                @if(count($transactions) - $offset >  $offset)
                <x-ib-button 
                    class="btn btn-sm btn-ghost text-secondary"
                    wire:click="updateOffset({{ $perPage }})"
                >
                    {!! __('pagination.next') !!}
                </x-ib-button>
                @endif
            </span>
            
        </div>
    @endif

    <x-ib-alpine-modal 
        key="manage-transaction"
        title="{{ __('Manage Transaction') }}"
    >
        @livewire('manage-transaction-form', [
            'portfolio' => $portfolio, 
            'transaction' => $editingTransaction, 
        ], key($editingTransaction?->id.rand()))

    </x-ib-alpine-modal>
</div>