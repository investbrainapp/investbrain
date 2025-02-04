<?php

use App\Models\Transaction;
use App\Models\Portfolio;
use App\Rules\SymbolValidationRule;
use App\Rules\QuantityValidationRule;
use Illuminate\Support\Collection;
use Livewire\Attributes\{Computed};
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Illuminate\Validation\Rule;
use App\Traits\WithTrimStrings;

new class extends Component {
    use Toast;
    use WithTrimStrings;

    // props
    public ?Portfolio $portfolio;
    public ?Transaction $transaction;

    public ?String $portfolio_id;
    public String $symbol;
    public String $transaction_type;
    public String $date;
    public Float $quantity;
    public ?Float $cost_basis;
    public ?Float $sale_price;

    public Bool $confirmingTransactionDeletion = false;

    // methods
    public function rules()
    {
        return [
            'symbol' => ['required', 'string', new SymbolValidationRule],
            'transaction_type' => 'required|string|in:BUY,SELL',
            'portfolio_id' => 'required|exists:portfolios,id',
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . now()->format('Y-m-d')],
            'quantity' => [
                'required', 
                'numeric', 
                'min:0', 
                new QuantityValidationRule($this->portfolio, $this->symbol, $this->transaction_type, $this->date)
            ],
            'cost_basis' => 'exclude_if:transaction_type,SELL|min:0|numeric',
            'sale_price' => 'exclude_if:transaction_type,BUY|min:0|numeric',
        ];
    }

    public function mount() 
    {
        if (isset($this->transaction)) {

            $this->symbol = $this->transaction->symbol;
            $this->transaction_type = $this->transaction->transaction_type;
            $this->portfolio_id = $this->transaction->portfolio_id;
            $this->date = $this->transaction->date->format('Y-m-d');
            $this->quantity = $this->transaction->quantity;
            $this->cost_basis = $this->transaction->cost_basis;
            $this->sale_price = $this->transaction->sale_price;
            
        } else {
            $this->transaction_type = 'BUY';
            $this->portfolio_id = isset($this->portfolio) ? $this->portfolio->id : '';
            $this->date = now()->format('Y-m-d');
        }
    }

    public function update()
    {
        $this->authorize('fullAccess', $this->portfolio);
        
        $this->transaction->update($this->validate());
        // $this->transaction->owner_id = auth()->user()->id;
        $this->transaction->save();

        $this->success(__('Transaction updated'));

        $this->dispatch('toggle-manage-transaction');
        $this->dispatch('transaction-updated');
    }

    public function save()
    {
        if (!isset($this->portfolio)) {
            $this->portfolio = Portfolio::find($this->portfolio_id);
        }

        $this->authorize('fullAccess', $this->portfolio);

        $validated = $this->validate();

        $transaction = $this->portfolio->transactions()->create($validated);
        $transaction->save();

        $this->dispatch('transaction-saved');

        $this->success(__('Transaction created'), redirectTo: route('holding.show', ['portfolio' => $this->portfolio->id, 'symbol' => $this->symbol]));
    }

    public function delete()
    {
        $this->authorize('fullAccess', $this->portfolio);

        $this->transaction->delete();

        $this->success(__('Transaction deleted'), redirectTo: route('holding.show', ['portfolio' => $this->portfolio->id, 'symbol' => $this->symbol]));
    }
}; ?>

<div class="" x-data="{ transaction_type: @entangle('transaction_type') }">
    <x-ib-form wire:submit="{{ $transaction ? 'update' : 'save' }}" class="">

        @if(empty($portfolio))

            <x-select 
                label="{{ __('Portfolio') }}" 
                wire:model="portfolio_id" 
                required 
                :options="auth()->user()->portfolios()->fullAccess()->get()"
                option-label="title" 
                placeholder="Select a portfolio"
            />
        @endif

        <x-input label="{{ __('Symbol') }}" wire:model="symbol" required />

        <x-select label="{{ __('Transaction Type') }}" :options="[
            ['id' => 'BUY', 'name' => 'Buy'], 
            ['id' => 'SELL', 'name' => 'Sell']
        ]" wire:model.live="transaction_type" />
        
        <x-datetime label="{{ __('Transaction Date') }}" wire:model="date" required />
        
        <x-input label="{{ __('Quantity') }}" type="number" step="any" wire:model="quantity" required />
        
        @if($transaction_type == 'SELL')
            <x-input 
                label="{{ __('Sale Price') }}" 
                wire:model.number="sale_price" 
                required 
                prefix="USD" 
                type="number"
                step="any"
            />
            {{-- money --}}
        @else
            <x-input 
                label="{{ __('Cost Basis') }}" 
                wire:model.number="cost_basis" 
                required 
                prefix="USD" 
                type="number"
                step="any"
            />
            {{-- money --}}
        @endif

        <x-slot:actions>
            @if ($transaction)
                <x-button 
                    wire:click="$toggle('confirmingTransactionDeletion')" 
                    wire:loading.attr="disabled"
                    class="btn text-error" 
                    title="{{ __('Delete Transaction') }}"
                    label="{{ __('Delete Transaction') }}"
                />
            @endif

            <x-button 
                label="{{ $transaction ? __('Update') : __('Create') }}" 
                type="submit" 
                icon="o-paper-airplane" 
                class="btn-primary" 
                spinner="{{ $transaction ? 'update' : 'save' }}"
            />
        </x-slot:actions>
    </x-ib-form>

    <x-confirmation-modal wire:model.live="confirmingTransactionDeletion">
        <x-slot name="title">
            {{ __('Delete Transaction') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you want to delete this transaction?') }}
        </x-slot>

        <x-slot name="footer">
            <x-button class="btn-outline" wire:click="$toggle('confirmingTransactionDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-button class="ms-3 btn-error text-white" wire:click="delete" wire:loading.attr="disabled">
                {{ __('Delete Transaction') }}
            </x-button>
        </x-slot>
    </x-confirmation-modal>
</div>