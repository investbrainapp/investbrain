<x-layouts.app>

        <x-ib-toolbar title="{{ __('Dashboard') }}"></x-ib-toolbar>
    
        @livewire('portfolio-performance-chart', [
            'name' => 'dashboard'
        ])

        <div class="grid sm:grid-cols-5 gap-5">
            <x-ib-card dense="true" sub-title="{{ __('Market Gain/Loss') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_market_gain_dollars', 0)) }} </div>
            </x-ib-card>
            
            <x-ib-card dense="true" sub-title="{{ __('Total Cost Basis') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_cost_basis', 0)) }} </div>
            </x-ib-card>
            
            <x-ib-card dense="true" sub-title="{{ __('Total Market Value') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_market_value', 0)) }} </div>
            </x-ib-card>
            
            <x-ib-card dense="true" sub-title="{{ __('Realized Gain/Loss') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('realized_gain_dollars', 0)) }} </div>
            </x-ib-card>

            <x-ib-card dense="true" sub-title="{{ __('Dividends Earned') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_dividends_earned', 0)) }} </div>
            </x-ib-card>
                
        </div>

        <div class="mt-6 grid md:grid-cols-7 gap-5">

            <x-ib-card title="{{ __('My portfolios') }}" class="md:col-span-4">

                @if ($user->portfolios->isEmpty())
                    <div class="flex justify-center items-center h-[100px] mb-8">
                        
                        <x-ib-button label="{{ __('Import / Export Data') }}" class="btn-primary btn-outline mr-6" link="{{ route('import-export') }}" />
                        <span>{{ __('or') }}</span>
                        <x-ib-button label="{{ __('Create your first portfolio!') }}" class="btn-primary ml-6" link="{{ route('portfolio.create') }}" />
                        
                    </div>
                @endif
                
                @foreach($user->portfolios as $portfolio)
                    <x-ib-list-item no-separator :item="$portfolio" link="{{ route('portfolio.show', ['portfolio' => $portfolio->id]) }}">
                        <x-slot:value>
                            {{ $portfolio->title }}
                            @if($portfolio->wishlist)
                                <x-badge value="{{ __('Wishlist') }}" class="badge-neutral badge-sm ml-2" />
                            @endif
                        </x-slot:value>
                    </x-ib-list-item>
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