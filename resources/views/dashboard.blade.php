<x-layouts.app>
    
        @livewire('portfolio-performance-chart', [
            'name' => 'dashboard'
        ])

        <div class="grid sm:grid-cols-5 gap-5">
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Market Gain/Loss') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_market_gain_dollars', 0)) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Total Cost Basis') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_cost_basis', 0)) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Total Market Value') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_market_value', 0)) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Realized Gain/Loss') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('realized_gain_dollars', 0)) }} </div>
            </x-card>

            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Dividends Earned') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_dividends_earned', 0)) }} </div>
            </x-card>
                
        </div>

        <div class="mt-6 grid md:grid-cols-7 gap-5">

            <x-ib-card title="{{ __('My portfolios') }}" class="md:col-span-4">

                @if ($user->portfolios->isEmpty())
                    <div class="flex justify-center items-center h-[100px] mb-8">
                        
                        <x-button label="{{ __('Import / Export Data') }}" class="btn-primary btn-outline mr-6" link="{{ route('import-export') }}" />
                        <span>{{ __('or') }}</span>
                        <x-button label="{{ __('Create your first portfolio!') }}" class="btn-primary ml-6" link="{{ route('portfolio.create') }}" />
                        
                    </div>
                @endif
                
                @foreach($user->portfolios as $portfolio)
                    <x-list-item :item="$portfolio" link="{{ route('portfolio.show', ['portfolio' => $portfolio->id]) }}">
                        <x-slot:value>
                            {{ $portfolio->title }}
                            @if($portfolio->wishlist)
                                <x-badge value="{{ __('Wishlist') }}" class="badge-secondary badge-sm ml-2" />
                            @endif
                        </x-slot:value>
                    </x-list-item>
                @endforeach

            </x-ib-card>

            @if (!$user->transactions->isEmpty())
            <x-ib-card title="{{ __('Recent activity') }}" class="md:col-span-3">
                    
                @livewire('transactions-list', [
                    'transactions' => $user->transactions,
                    'showPortfolio' => true,
                    'paginate' => false
                ])

            </x-ib-card>
            @endif

            @if (!$user->portfolios->isEmpty())
            <x-ib-card title="{{ __('Top performers') }}" class="md:col-span-3">

                @livewire('top-performers-list', [
                    'holdings' => $user->holdings
                ])

            </x-ib-card>
            @endif

        </div>
    </div>
    
</x-layouts.app>