<?php

use App\Interfaces\MarketData\MarketDataInterface;
use App\Models\Currency;
use App\Models\MarketData;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Rules\QuantityValidationRule;
use App\Rules\SymbolValidationRule;
use App\Traits\Toast;
use App\Traits\WithTrimStrings;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;
    use WithTrimStrings;

    // props
    public ?Portfolio $portfolio;

    public ?Transaction $transaction = null;

    public ?string $portfolio_id;

    public string $symbol = '';

    public string $transaction_type;

    public string $date;

    public float $quantity;

    public ?float $cost_basis;

    public ?float $sale_price;

    public bool $confirmingTransactionDeletion = false;

    public Collection $currencies;

    public string $currency;

    public array $symbolSuggestions = [];

    public bool $skipSymbolSearch = false;

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
                new QuantityValidationRule($this->portfolio, $this->symbol, $this->transaction_type, $this->date, $this->transaction),
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

            if (isset($this->symbol) && $this->symbol !== '') {

                $this->currency = MarketData::getMarketData($this->symbol)?->currency;
            }

            $this->transaction_type = 'BUY';
            $this->portfolio_id = isset($this->portfolio) ? $this->portfolio->id : '';
            $this->date = now()->toDateString();
        }
    }

    public function updatedSymbol(string $value): void
    {
        if ($this->skipSymbolSearch) {
            $this->skipSymbolSearch = false;

            return;
        }

        $query = trim($value);

        if (strlen($query) < 2) {
            $this->symbolSuggestions = [];

            return;
        }

        $this->symbolSuggestions = app(MarketDataInterface::class)
            ->search($query)
            ->take(8)
            ->map(fn ($result) => [
                'symbol' => $result->getSymbol(),
                'name' => $result->getName(),
                'type' => $result->getType(),
                'exchange' => $result->getExchange(),
            ])
            ->values()
            ->all();
    }

    public function selectSymbol(string $symbol): void
    {
        $this->skipSymbolSearch = true;
        $this->symbol = $symbol;
        $this->symbolSuggestions = [];

        $this->currency = MarketData::getMarketData($symbol)?->currency ?? $this->currency;
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
    <x-ui.form wire:submit="{{ $transaction ? 'update' : 'save' }}" class="">

        @if(empty($portfolio))

            <x-ui.select 
                label="{{ __('Portfolio') }}" 
                wire:model="portfolio_id" 
                required 
                :options="auth()->user()->portfolios()->fullAccess()->get()"
                option-label="title" 
                placeholder="Select a portfolio"
            />
        @endif

        <div
            class="relative"
            x-data="{ open: false }"
            @click.outside="open = false"
            @keydown.escape.window="open = false"
        >
            <x-ui.input
                label="{{ __('Symbol') }}"
                wire:model.live.debounce.300ms="symbol"
                required
                autocomplete="off"
                @focus="open = true"
                @input="open = true"
            />

            <div
                x-show="open && $wire.symbolSuggestions.length"
                x-cloak
                class="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-lg"
            >
                <ul class="max-h-72 overflow-y-auto py-1">
                    @foreach ($symbolSuggestions as $suggestion)
                        <li wire:key="symbol-suggestion-{{ $suggestion['symbol'] }}-{{ $loop->index }}">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-base-200"
                                wire:click="selectSymbol(@js($suggestion['symbol']))"
                                @click="open = false"
                            >
                                <div class="avatar avatar-placeholder">
                                    <div class="w-9 rounded-full bg-neutral text-neutral-content">
                                        <span class="text-xs">{{ strtoupper(substr($suggestion['symbol'], 0, 2)) }}</span>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold">{{ $suggestion['name'] ?: $suggestion['symbol'] }}</div>
                                    <div class="flex items-center gap-2 text-sm text-base-content/50">
                                        <span>{{ $suggestion['symbol'] }}</span>
                                        @if(!empty($suggestion['type']))
                                            <x-ui.badge class="badge-sm badge-ghost" :value="$suggestion['type']" />
                                        @endif
                                    </div>
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <x-ui.select label="{{ __('Transaction Type') }}" :options="[
            ['id' => 'BUY', 'name' => 'Buy'], 
            ['id' => 'SELL', 'name' => 'Sell']
        ]" wire:model.live="transaction_type" />
        
        <x-ui.datetime label="{{ __('Transaction Date') }}" wire:model="date" required />
        
        <x-ui.input label="{{ __('Quantity') }}" type="number" step="any" wire:model="quantity" required />
        
        @if($transaction_type == 'SELL')
            <x-ui.input 
                label="{{ __('Sale Price') }}" 
                wire:model.number="sale_price" 
                required 
                type="number"
                step="any"
            >
                <x-slot:prepend>
                    
                    <x-ui.select 
                        class="rounded-e-none border-e-0 bg-base-200"
                        icon="o-banknotes"
                        :options="$currencies"
                        option-value="currency"
                        option-label="currency"
                        wire:model="currency"
                        id="currency"
                    />
                </x-slot:prepend>
            </x-ui.input>
        @else
            <x-ui.input 
                label="{{ __('Cost Basis') }}" 
                wire:model.number="cost_basis" 
                required 
                type="number"
                step="any"
            >
                <x-slot:prepend>

                    <x-ui.select 
                        class="rounded-e-none border-e-0 bg-base-200"
                        icon="o-banknotes"
                        :options="$currencies"
                        option-value="currency"
                        option-label="currency"
                        wire:model="currency"
                        id="currency"
                    />
                </x-slot:prepend>
             
            </x-ui.input>
        @endif

        <x-slot:actions>
            @if ($transaction)
                <x-ui.button 
                    wire:click="$toggle('confirmingTransactionDeletion')" 
                    wire:loading.attr="disabled"
                    class="btn text-error" 
                    title="{{ __('Delete Transaction') }}"
                    label="{{ __('Delete Transaction') }}"
                />
            @endif

            <x-ui.button 
                label="{{ $transaction ? __('Update') : __('Create') }}" 
                type="submit" 
                icon="o-paper-airplane" 
                class="btn-primary" 
                spinner="{{ $transaction ? 'update' : 'save' }}"
            />
        </x-slot:actions>
    </x-ui.form>

    <x-ui.confirmation-modal wire:model.live="confirmingTransactionDeletion">
        <x-slot name="title">
            {{ __('Delete Transaction') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you want to delete this transaction?') }}
        </x-slot>

        <x-slot name="footer">
            <x-ui.button class="btn-outline" wire:click="$toggle('confirmingTransactionDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-ui.button>

            <x-ui.button class="ms-3 btn-error text-white" wire:click="delete" wire:loading.attr="disabled">
                {{ __('Delete Transaction') }}
            </x-ui.button>
        </x-slot>
    </x-ui.confirmation-modal>
</div>
