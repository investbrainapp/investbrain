<?php

use App\Models\Currency;
use App\Models\MarketData;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Rules\QuantityValidationRule;
use App\Rules\SymbolValidationRule;
use App\Traits\WithTrimStrings;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;
    use WithTrimStrings;

    // props
    public ?Portfolio $portfolio;

    public ?Transaction $transaction;

    public ?string $portfolio_id;

    public string $symbol;

    public string $transaction_type;

    public string $date;

    public float $quantity;

    public ?float $cost_basis;

    public ?float $sale_price;

    public bool $confirmingTransactionDeletion = false;

    public Collection $currencies;

    public string $currency;

    // methods
    public function rules()
    {
        return [
            'symbol' => ['required', 'string', new SymbolValidationRule],
            'transaction_type' => 'required|string|in:BUY,SELL',
            'portfolio_id' => 'required|exists:portfolios,id',
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->toDateString()],
            'quantity' => [
                'required',
                'numeric',
                'gt:0',
                new QuantityValidationRule($this->portfolio, $this->symbol, $this->transaction_type, $this->date),
            ],
            'currency' => ['required', 'exists:currencies,currency'],
            'cost_basis' => 'exclude_if:transaction_type,SELL|min:0|numeric',
            'sale_price' => 'exclude_if:transaction_type,BUY|min:0|numeric',
        ];
    }

    public function mount()
    {
        $this->currencies = Currency::list();
        $this->currency = auth()->user()->getCurrency();

        if (isset($this->transaction)) {

            $this->currency = $this->transaction->market_data->currency;

            $this->symbol = $this->transaction->symbol;
            $this->transaction_type = $this->transaction->transaction_type;
            $this->portfolio_id = $this->transaction->portfolio_id;
            $this->date = $this->transaction->date->toDateString();
            $this->quantity = $this->transaction->quantity;
            $this->cost_basis = $this->transaction->cost_basis;
            $this->sale_price = $this->transaction->sale_price;

        } else {

            if (isset($this->symbol)) {

                $this->currency = MarketData::getMarketData($this->symbol)?->currency;
            }

            $this->transaction_type = 'BUY';
            $this->portfolio_id = isset($this->portfolio) ? $this->portfolio->id : '';
            $this->date = now()->toDateString();
        }
    }

    public function update()
    {
        $this->authorize('fullAccess', $this->portfolio);

        $this->transaction->update($this->validate());
        $this->transaction->save();

        $this->success(__('Transaction updated'));

        $this->dispatch('toggle-manage-transaction');
        $this->dispatch('transaction-updated');
    }

    public function save()
    {
        if (! isset($this->portfolio)) {
            $this->portfolio = Portfolio::find($this->portfolio_id);
        }

        $this->authorize('fullAccess', $this->portfolio);

        $validated = $this->validate();

        $transaction = $this->portfolio->transactions()->create($validated);
        $transaction->save();

        $this->dispatch('transaction-saved');

        $this->success(__('Transaction created'), redirectTo: route('holding.show', ['portfolio' => $this->portfolio->id, 'symbol' => $transaction->symbol]));
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
                type="number"
                step="any"
            >
                <x-slot:prepend>
                    
                    <x-select 
                        class="rounded-e-none border-e-0 bg-base-200"
                        icon="o-banknotes"
                        :options="$currencies"
                        option-value="currency"
                        option-label="currency"
                        wire:model="currency"
                        id="currency"
                    />
                </x-slot:prepend>
            </x-input>
        @else
            <x-input 
                label="{{ __('Cost Basis') }}" 
                wire:model.number="cost_basis" 
                required 
                type="number"
                step="any"
            >
                <x-slot:prepend>

                    <x-select 
                        class="rounded-e-none border-e-0 bg-base-200"
                        icon="o-banknotes"
                        :options="$currencies"
                        option-value="currency"
                        option-label="currency"
                        wire:model="currency"
                        id="currency"
                    />
                </x-slot:prepend>
             
            </x-input>
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