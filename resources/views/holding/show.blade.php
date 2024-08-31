<x-app-layout>
    <div x-data>  

        <x-ib-modal 
            key="new-transaction"
            title="New Transaction"
        >
            @livewire('manage-transaction-form', [
                'portfolio' => $portfolio, 
                'symbol' => $holding->market_data->symbol, 
            ])

        </x-ib-modal>

        <x-ib-toolbar>
            <x-slot:title>
                <a href="{{ route('portfolio.show', ['portfolio' => $portfolio->id]) }}" title="{{ __('Portfolio') }}">
                    {{ $portfolio->title }}
                </a> » <span title="{{ __('Holding') }}">{{ $holding->market_data->symbol }}</span>
            </x-slot:title>

            <x-ib-flex-spacer />
            
            <div>
                <x-button 
                    label="{{ __('Create Transaction') }}" 
                    class="btn-sm btn-primary" 
                    @click="$dispatch('toggle-new-transaction')"
                />
            </div>
        </x-ib-toolbar>

        <div class="mt-6 grid md:grid-cols-9 gap-5">

            <x-ib-card class="md:col-span-5">
                <x-slot:title class="pb-2">

                    {{ $holding->market_data->symbol }} 
                    <span class="text-sm"> {{ $holding->market_data->name }} </span>
                </x-slot:title>

                @livewire('holding-market-data', ['holding' => $holding])

            </x-ib-card>

            <x-ib-card title="{{ __('Fundamentals') }}" class="md:col-span-4">

                <p>
                    <span class="font-bold">{{ __('Forward PE') }}: </span>
                    {{ $holding->market_data->forward_pe }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Trailing PE') }}: </span>
                    {{ $holding->market_data->trailing_pe }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Market Cap') }}: </span>
                    ${{ Number::forHumans($holding->market_data->market_cap ?? 0) }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('52 week') }}: </span>

                    <x-fifty-two-week-range 
                        :low="$holding->market_data->fifty_two_week_low" 
                        :high="$holding->market_data->fifty_two_week_high" 
                        :current="$holding->market_data->market_value"
                    />
                    
                </p>
                
            </x-ib-card>

            <x-ib-card title="{{ __('Recent activity') }}" class="md:col-span-3">

                @livewire('transactions-list', [
                    'portfolio' => $holding->portfolio,
                    'transactions' => $holding->transactions,
                    'shouldGoToHolding' => false
                ])

            </x-ib-card>

            <x-ib-card title="{{ __('Dividends') }}" class="md:col-span-3">

                @livewire('holding-dividends-list', ['holding' => $holding])

            </x-ib-card>

            <x-ib-card title="{{ __('Splits') }}" class="md:col-span-3">

                @foreach ($holding->splits->take(5) as $split)

                    <x-list-item :item="$split">
                        <x-slot:value>
        
                           1:{{ $split->split_amount }}

                        </x-slot:value>
                        <x-slot:sub-value>
                            {{ $split->date->format('F d, Y') }}
                        </x-slot:sub-value>
                    </x-list-item>
                
                @endforeach

            </x-ib-card>

        </div>

    </div>
</x-app-layout>
