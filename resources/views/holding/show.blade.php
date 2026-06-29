@use('App\Models\Currency')
@use('Illuminate\Support\Number')

<x-layouts.app>
    <div x-data>  

        <x-ui.modal 
            key="create-transaction"
            title="{{ __('Create Transaction') }}"
        >
            @livewire('manage-transaction-form', [
                'portfolio' => $portfolio, 
                'symbol' => $holding->market_data->symbol, 
            ])

        </x-ui.modal>

        <x-ui.modal 
            key="holding-options"
            title="{{ __('Holding Options') }}"
        >
            @livewire('holding-options-form', [
                'holding' => $holding
            ])

        </x-ui.modal>

        <x-ui.toolbar>
            <x-slot:title>
                <a href="{{ route('portfolio.show', ['portfolio' => $portfolio->id]) }}" title="{{ __('Portfolio') }}">
                    {{ $portfolio->title }}
                </a> » <span title="{{ __('Holding') }}">{{ $holding->market_data->symbol }}</span>

            </x-slot:title>

            @can('fullAccess', $portfolio)
            <x-ui.button 
                title="{{ __('Holding options') }}" 
                icon="o-pencil" 
                class="btn-circle btn-ghost btn-sm text-secondary" 
                @click="$dispatch('toggle-holding-options')"
            />
            @else
            <x-ui.icon name="o-eye" class="text-secondary w-4" title="{{ __('Read only') }}" />
            @endcan

            <x-ui.flex-spacer />
            
            @can('fullAccess', $portfolio)
            <x-ui.button 
                label="{{ __('Create transaction') }}" 
                class="btn-sm btn-primary whitespace-nowrap" 
                @click="$dispatch('toggle-create-transaction')"
            />
            @endcan
        </x-ui.toolbar>

        <div class="mt-6 grid md:grid-cols-9 gap-5">

            <x-ui.card class="md:col-span-5">
                <x-slot:title>

                    {{ $holding->market_data->symbol }} 
                    <span class="text-sm ml-2"> {{ $holding->market_data->name }} </span>
                </x-slot:title>

                @livewire('holding-market-data', ['holding' => $holding])

            </x-ui.card>

            <x-ui.card title="{{ __('Fundamentals') }}" class="md:col-span-4">

                @if(!empty($holding->market_data->market_cap))
                <p>
                    <span class="font-bold">{{ __('Market Cap') }}: </span>
                    {{ Currency::forHumans($holding->market_data->market_cap, $holding->market_data->currency) }}
                </p>
                @endif

                @if(!empty($holding->market_data->forward_pe))
                <p>
                    <span class="font-bold">{{ __('Forward PE') }}: </span>
                    {{ $holding->market_data->forward_pe }} 
                </p>
                @endif

                @if(!empty($holding->market_data->trailing_pe))
                <p>
                    <span class="font-bold">{{ __('Trailing PE') }}: </span>
                    {{ $holding->market_data->trailing_pe }} 
                </p>
                @endif

                @if(!empty($holding->market_data->book_value))
                    <p>
                        <span class="font-bold">{{ __('Book Value') }}: </span>
                        {{ Number::currency($holding->market_data->book_value, $holding->market_data->currency) }} 
                    </p>
                @endif

                <p>
                    <span class="font-bold">{{ __('52 week') }}: </span>

                    <x-ui.fifty-two-week-range :market-data="$holding->market_data" />
                </p>

                @if(!empty($holding->market_data->dividend_yield))
                <p>
                    <span class="font-bold">{{ __('Dividend Yield') }}: </span>
                    {{ Number::percentage(
                        $holding->market_data->dividend_yield, 
                        $holding->market_data->dividend_yield < 1 ? 2 : 0
                    ) }} 
                </p>
                @endif

                @if(!empty($holding->market_data->last_dividend_date))
                <p>
                    <span class="font-bold">{{ __('Last Dividend Paid') }}: </span>
                    {{ $holding->market_data->last_dividend_date->format('F d, Y') }} 
                </p>
                @endif
                
            </x-ui.card>

            @if(config('services.adanos.key'))
            <x-ui.card title="{{ __('Market sentiment') }}" class="md:col-span-4">
                @if($holding->market_sentiment)
                <div class="space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="text-secondary">{{ __('Buzz') }}</div>
                            <div class="font-semibold">{{ Number::format($holding->market_sentiment->average_buzz ?? 0, precision: 1) }}/100</div>
                        </div>
                        <div>
                            <div class="text-secondary">{{ __('Bullish') }}</div>
                            <div class="font-semibold">{{ Number::percentage(($holding->market_sentiment->average_bullish_pct ?? 0) / 100, 0) }}</div>
                        </div>
                        <div>
                            <div class="text-secondary">{{ __('Coverage') }}</div>
                            <div class="font-semibold">{{ $holding->market_sentiment->coverage }} {{ __('sources') }}</div>
                        </div>
                        <div>
                            <div class="text-secondary">{{ __('Alignment') }}</div>
                            <div class="font-semibold">{{ str($holding->market_sentiment->source_alignment)->replace('-', ' ')->title() }}</div>
                        </div>
                    </div>

                    <div class="space-y-2 border-t pt-3">
                        @if(!is_null($holding->market_sentiment->reddit_buzz))
                        <p>
                            <span class="font-bold">Reddit:</span>
                            {{ __('Buzz') }} {{ Number::format($holding->market_sentiment->reddit_buzz, precision: 1) }},
                            {{ __('Bullish') }} {{ Number::percentage(($holding->market_sentiment->reddit_bullish_pct ?? 0) / 100, 0) }},
                            {{ __('Mentions') }} {{ Number::format($holding->market_sentiment->reddit_mentions ?? 0) }}
                        </p>
                        @endif

                        @if(!is_null($holding->market_sentiment->x_buzz))
                        <p>
                            <span class="font-bold">X:</span>
                            {{ __('Buzz') }} {{ Number::format($holding->market_sentiment->x_buzz, precision: 1) }},
                            {{ __('Bullish') }} {{ Number::percentage(($holding->market_sentiment->x_bullish_pct ?? 0) / 100, 0) }},
                            {{ __('Mentions') }} {{ Number::format($holding->market_sentiment->x_mentions ?? 0) }}
                        </p>
                        @endif

                        @if(!is_null($holding->market_sentiment->news_buzz))
                        <p>
                            <span class="font-bold">{{ __('Finance News') }}:</span>
                            {{ __('Buzz') }} {{ Number::format($holding->market_sentiment->news_buzz, precision: 1) }},
                            {{ __('Bullish') }} {{ Number::percentage(($holding->market_sentiment->news_bullish_pct ?? 0) / 100, 0) }},
                            {{ __('Mentions') }} {{ Number::format($holding->market_sentiment->news_mentions ?? 0) }}
                        </p>
                        @endif

                        @if(!is_null($holding->market_sentiment->polymarket_buzz))
                        <p>
                            <span class="font-bold">Polymarket:</span>
                            {{ __('Buzz') }} {{ Number::format($holding->market_sentiment->polymarket_buzz, precision: 1) }},
                            {{ __('Bullish') }} {{ Number::percentage(($holding->market_sentiment->polymarket_bullish_pct ?? 0) / 100, 0) }},
                            {{ __('Trades') }} {{ Number::format($holding->market_sentiment->polymarket_trade_count ?? 0) }}
                        </p>
                        @endif
                    </div>

                    <p class="text-xs text-secondary">
                        {{ __('Updated :time', ['time' => $holding->market_sentiment->updated_at?->diffForHumans()]) }}
                    </p>
                </div>
                @else
                <div class="flex justify-center items-center h-full pb-10 text-secondary">
                    {{ __('No market sentiment is available for :symbol right now.', ['symbol' => $holding->symbol]) }}
                </div>
                @endif
            </x-ui.card>
            @endif

            <x-ui.card title="{{ __('Recent activity') }}" class="md:col-span-3">

                @livewire('transactions-list', [
                    'portfolio' => $holding->portfolio,
                    'transactions' => $holding->transactions,
                    'shouldGoToHolding' => false
                ])

            </x-ui.card>

            <x-ui.card title="{{ __('Dividends') }}" class="md:col-span-3">

                @if($holding->dividends->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('No dividends for :symbol yet', ['symbol' => $holding->symbol]) }}
                    </div>

                @endif

                @livewire('holding-dividends-list', ['holding' => $holding])

            </x-ui.card>

            <x-ui.card title="{{ __('Splits') }}" class="md:col-span-3">

                @if($holding->splits->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('No splits for :symbol yet', ['symbol' => $holding->symbol]) }}
                    </div>

                @endif

                @foreach ($holding->splits->take(5) as $split)

                    <x-ui.list-item :item="$split" no-separator no-hover>
                        <x-slot:value>
        
                        1:{{ $split->split_amount }}

                        </x-slot:value>
                        <x-slot:sub-value>
                            <span title="{{ __('Distribution Date') }}">{{ $split->date->format('F d, Y') }}</span>
                        </x-slot:sub-value>
                    </x-ui.list-item>
                
                @endforeach

            </x-ui.card>

            @if(config('services.ai_chat_enabled'))
            @livewire('ui.ai-chat-window', [
                'chatable' => $holding,
                'suggested_prompts' => [
                    [
                        'text' => 'What are the key risks?',
                        'value' => 'What are the key risks for the holding?'
                    ],
                    [
                        'text' => 'Should I invest more?',
                        'value' => 'Is it worthwhile to invest more?'
                    ],
                    [
                        'text' => 'Should I sell?',
                        'value' => 'When would be a good time for me to sell?'
                    ],
                    [
                        'text' => 'What are the key strengths?',
                        'value' => 'Can you tell me the key strengths for this holding?'
                    ],
                    [
                        'text' => 'Is this a successful position?',
                        'value' => 'Is this a successful holding in my portfolio?'
                    ]
                ],
            ])
            @endif

        </div>

    </div>
</x-layouts.app>
