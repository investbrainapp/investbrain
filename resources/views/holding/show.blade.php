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
            @else
            <x-icon name="o-eye" class="text-secondary w-4" title="{{ __('Read only') }}" />
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
                    {{ Number::forHumans($holding->market_data->market_cap) }}
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
                    {{ Number::currency($holding->market_data->book_value, $holding->market_data->currency) }} 
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

            @if(config('services.ai_chat_enabled'))
            {{-- // TODO: add to system prompt:
                    // Additionally, here is some recent news about {$this->holding->symbol}:
                    // And their latest SEC filings: --}}
            @livewire('ai-chat-window', [
                'chatable' => $holding,
                'suggested_prompts' => [
                    [
                        'text' => 'What are the key risks?',
                        'value' => 'What are the key risks for the company?'
                    ],
                    [
                        'text' => 'Should I invest more?',
                        'value' => 'Is it worthwhile to invest more?'
                    ],
                    [
                        'text' => 'Should I sell?',
                        'value' => 'When is a good time for me to sell?'
                    ],
                    [
                        'text' => 'What are the key strengths?',
                        'value' => 'What are the key strengths for this company?'
                    ],
                    [
                        'text' => 'Is this a successful position?',
                        'value' => 'Is this a successful holding in my portfolio?'
                    ]
                ],
                'system_prompt' => "
                        You are an investment portfolio assistant providing advice to an investor.  Use the following information to provide relevant recommendations.  Use the words 'likely' or 'may' instead of concrete statements (except for obvious statements of fact or common sense):

                        The investor owns ". ($holding->quantity > 0 ? 'a total of '.$holding->quantity : 'ZERO') ." shares of {$holding->market_data->name} (ticker: {$holding->symbol}) with an average cost basis of {$holding->average_cost_basis}. Here are the relevant transactions - sales and purchases of {$holding->symbol}:

                        {$formattedTransactions}

                        This investor has earned $ {$holding->dividends_earned} in dividends so far and earned {$holding->realized_gains_dollars} in realized gains (sales) from {$holding->symbol} in this portfolio.

                        The current market price for {$holding->symbol} is {$holding->market_data->market_value}. Additionally, here's other critical fundamentals for {$holding->market_data->name} that might help:
                         * Market cap: {$holding->market_data->market_cap}
                         * Forward PE: {$holding->market_data->forward_pe}
                         * Trailing PE: {$holding->market_data->trailing_pe}
                         * Book value: {$holding->market_data->book_value}
                         * 52 week low: {$holding->market_data->fifty_two_week_low}
                         * 52 week high: {$holding->market_data->fifty_two_week_high}
                         * Dividend yield: {$holding->market_data->dividend_yield}
                        
                        This data is current as of today's date: " . now()->format('Y-m-d') . ". Based on this current market data, quantity owned, and average cost basis, you should determine if the {$holding->symbol} holding is making or losing money.

                        Below is the question from the investor. Considering these facts, provide a concise response to the following question (give a direct response). Limit your response to no more than 75 words and consider using a common decision framework. Use github style markdown for any formatting:"
            ])
            @endif

        </div>

    </div>
</x-app-layout>
