<x-app-layout>
    <div x-data>

        <x-ib-modal 
            key="create-transaction"
            title="{{ __('Create Transaction') }}"
        >
            @livewire('manage-transaction-form', [
                'portfolio' => $portfolio, 
            ])

        </x-ib-modal>

        <x-ib-drawer 
            key="manage-portfolio"
            title="{{ $portfolio->title }}"
        >
            @livewire('manage-portfolio-form', [
                'portfolio' => $portfolio, 
                'hideCancel' => true
            ])

        </x-ib-drawer>

        <x-ib-toolbar :title="$portfolio->title">

            @if($portfolio->wishlist)
            <x-badge value="{{ __('Wishlist') }}" class="badge-secondary mr-3" />
            @endif

            <x-button 
                title="{{ __('Edit Portfolio') }}" 
                icon="o-pencil" 
                class="btn-circle btn-ghost btn-sm text-secondary" 
                @click="$dispatch('toggle-manage-portfolio')"
            />

            <x-ib-flex-spacer />
            
            <div>
                <x-button 
                    label="{{ __('Create Transaction') }}" 
                    class="btn-sm btn-primary" 
                    @click="$dispatch('toggle-create-transaction')"
                />
            </div>
        </x-ib-toolbar>

        @livewire('portfolio-performance-chart', [
            'name' => 'portfolio-'.$portfolio->id,
            'portfolio' => $portfolio
        ])

        <div class="grid sm:grid-cols-5 gap-5">

            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Market Gain/Loss') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_gain_dollars) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Total Cost Basis') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_cost_basis) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Total Market Value') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_market_value) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Realized Gain/Loss') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->realized_gain_dollars) }} </div>
            </x-card>

            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap truncate">{{ __('Dividends Earned') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_dividends_earned) }} </div>
            </x-card>
                
        </div>

        <div class="mt-6 grid md:grid-cols-7 gap-5">

            <x-ib-card title="{{ __('Holdings') }}" class="md:col-span-4">

                @if($portfolio->holdings->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('Nothing to show here yet') }}
                    </div>

                @else

                    @livewire('holdings-table', [
                        'portfolio' => $portfolio
                    ])

                @endif
            </x-ib-card>

            <x-ib-card title="{{ __('Recent activity') }}" class="md:col-span-3">

                @if($portfolio->transactions->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('Nothing to show here yet') }}
                    </div>
                
                @endif

                @livewire('transactions-list', [
                    'portfolio' => $portfolio,
                    'transactions' => $portfolio->transactions
                ])

            </x-ib-card>

            <x-ib-card title="{{ __('Top performers') }}" class="md:col-span-3">

                @if($portfolio->holdings->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('Nothing to show here yet') }}
                    </div>

                @endif

                @livewire('top-performers-list', [
                    'holdings' => $portfolio->holdings
                ])

            </x-ib-card>

            {{-- <x-ib-card title="{{ __('Top headlines') }}" class="md:col-span-3">
            
                @php
                    $users = App\Models\User::take(3)->get();
                @endphp
                
                @foreach($users as $user)
                    <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
                @endforeach

            </x-ib-card> --}}

        </div>
    </div>
</x-app-layout>