<x-app-layout>
    <div x-data>  

        <x-ib-alpine-modal 
            key="create-transaction"
            title="{{ __('Create Transaction') }}"
        >
            @livewire('manage-transaction-form', [
                'portfolio' => $portfolio, 
                'symbol' => $holding->market_data->symbol, 
            ])

        </x-ib-alpine-modal>

        <x-ib-alpine-modal 
            key="holding-options"
            title="{{ __('Holding Options') }}"
        >
            @livewire('holding-options-form', [
                'holding' => $holding
            ])

        </x-ib-alpine-modal>

        <x-ib-toolbar>
            <x-slot:title>
                <a href="{{ route('portfolio.show', ['portfolio' => $portfolio->id]) }}" title="{{ __('Portfolio') }}">
                    {{ $portfolio->title }}
                </a> » <span title="{{ __('Holding') }}">{{ $holding->market_data->symbol }}</span>

            </x-slot:title>

            @can('fullAccess', $portfolio)
            <x-button 
                title="{{ __('Holding options') }}" 
                icon="o-pencil" 
                class="btn-circle btn-ghost btn-sm text-secondary" 
                @click="$dispatch('toggle-holding-options')"
            />
            @endcan

            <x-ib-flex-spacer />
            
            @can('fullAccess', $portfolio)
            <x-button 
                label="{{ __('Create transaction') }}" 
                class="btn-sm btn-primary whitespace-nowrap" 
                @click="$dispatch('toggle-create-transaction')"
            />
            @endcan
        </x-ib-toolbar>

        <div class="mt-6 grid md:grid-cols-9 gap-5">

            <x-ib-card class="md:col-span-5">
                <x-slot:title>

                    {{ $holding->market_data->symbol }} 
                    <span class="text-sm ml-2"> {{ $holding->market_data->name }} </span>
                </x-slot:title>

                @livewire('holding-market-data', ['holding' => $holding])

            </x-ib-card>

            <x-ib-card title="{{ __('Fundamentals') }}" class="md:col-span-4">

                <p>
                    <span class="font-bold">{{ __('Market Cap') }}: </span>
                    ${{ Number::forHumans($holding->market_data->market_cap ?? 0) }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Forward PE') }}: </span>
                    {{ $holding->market_data->forward_pe }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Trailing PE') }}: </span>
                    {{ $holding->market_data->trailing_pe }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Book Value') }}: </span>
                    {{ $holding->market_data->book_value }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('52 week') }}: </span>

                    <x-fifty-two-week-range 
                        :low="$holding->market_data->fifty_two_week_low" 
                        :high="$holding->market_data->fifty_two_week_high" 
                        :current="$holding->market_data->market_value"
                    />
                </p>

                <p>
                    <span class="font-bold">{{ __('Dividend Yield') }}: </span>
                    {{ Number::percentage(
                        $holding->market_data->dividend_yield ?? 0, 
                        $holding->market_data->dividend_yield < 1 ? 2 : 0
                    ) }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Last Dividend Paid') }}: </span>
                    {{ $holding->market_data?->last_dividend_date?->format('F d, Y') ?? '' }} 
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

                @if($holding->dividends->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('No dividends for :symbol yet', ['symbol' => $holding->symbol]) }}
                    </div>

                @endif

                @livewire('holding-dividends-list', ['holding' => $holding])

            </x-ib-card>

            <x-ib-card title="{{ __('Splits') }}" class="md:col-span-3">

                @if($holding->splits->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('No splits for :symbol yet', ['symbol' => $holding->symbol]) }}
                    </div>

                @endif

                @foreach ($holding->splits->take(5) as $split)

                    <x-list-item :item="$split">
                        <x-slot:value>
        
                        1:{{ $split->split_amount }}

                        </x-slot:value>
                        <x-slot:sub-value>
                            <span title="{{ __('Distribution Date') }}">{{ $split->date->format('F d, Y') }}</span>
                        </x-slot:sub-value>
                    </x-list-item>
                
                @endforeach

            </x-ib-card>

        </div>

    </div>
</x-app-layout>
