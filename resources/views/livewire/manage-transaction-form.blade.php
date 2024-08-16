<?php

use App\Models\Transaction;
use App\Models\Portfolio;
use Illuminate\Support\Collection;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Mary\Traits\Toast;
use Livewire\Attributes\Computed;

new class extends Component {
    use Toast;

    // props
    public ?Portfolio $portfolio;
    public ?Transaction $transaction;

    #[Rule('required|string|max:15')]
    public String $symbol;

    #[Rule('required|string|in:BUY,SELL')]
    public String $transaction_type;

    #[Rule('required|date')]
    public String $date;

    #[Rule('required|numeric')]
    public String $quantity;

    #[Rule('required|numeric')]
    public String $cost_basis;

    public Bool $confirmingTransactionDeletion = false;

    #[Computed]
    public function cost_basis_label()
    {
        if (isset($this->transaction_type) && $this->transaction_type == 'SELL') {
            return __('Sale Price');
        }
        
        return __('Cost Basis');
    }

    // methods
    public function mount() 
    {
        if (isset($this->transaction)) {

            $this->symbol = $this->transaction->symbol;
            $this->transaction_type = $this->transaction->transaction_type;
            $this->date = $this->transaction->date->format('Y-m-d');
            $this->quantity = $this->transaction->quantity;
            $this->cost_basis = $this->transaction_type == 'SELL' 
                ? $this->transaction->sale_price
                : $this->transaction->cost_basis;
            
        } else {
            $this->transaction_type = 'BUY';
            $this->date = now()->format('Y-m-d');
        }
    }

    public function update()
    {
        
        $this->transaction->update($this->validate());
        // $this->transaction->owner_id = auth()->user()->id;
        $this->transaction->save();

        $this->success(__('Transaction updated'), redirectTo: route('portfolio.show', ['portfolio' => $this->portfolio->id]));
    }

    public function save()
    {

        $transaction = $this->portfolio->transactions()->create($this->validate());
        $transaction->save();

        $this->success(__('Transaction created'), redirectTo: route('portfolio.show', ['portfolio' => $this->portfolio->id]));
    }

    public function delete()
    {

        $this->transaction->delete();

        $this->success(__('Transaction deleted'), redirectTo: route('portfolio.show', ['portfolio' => $this->portfolio->id]));
    }
}; ?>

<div class="">
    <x-form wire:submit="{{ $transaction ? 'update' : 'save' }}" class="">

        <x-input label="{{ __('Symbol') }}" wire:model="symbol" required />

        <x-select label="{{ __('Transaction Type') }}" :options="[
            ['id' => 'BUY', 'name' => 'Buy'], 
            ['id' => 'SELL', 'name' => 'Sell']
        ]" wire:model.live="transaction_type" />
        
        <x-datetime label="{{ __('Transaction Date') }}" wire:model="date" required />
        
        <x-input label="{{ __('Quantity') }}" type="number" step="any" wire:model="quantity" required />
        
        <x-input 
            label="{{ $this->cost_basis_label }}" 
            wire:model="cost_basis" 
            required 
            prefix="USD" 
            money
            type="number"
            step="any"
        />

        <x-slot:actions>
            @if ($transaction)
            <x-button 
                class="ms-3 btn btn-ghost text-error" 
                wire:click="$toggle('confirmingTransactionDeletion')" 
                wire:loading.attr="disabled"
                label="{{ __('Delete') }}"
                title="{{ __('Delete Transaction') }}"
            />
            @endif

            <x-button label="{{ $transaction ? 'Update' : 'Create' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-form>

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