<x-app-layout>
    <div x-data>

        <x-ib-modal 
            key="new-transaction"
            title="New Transaction"
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
                    @click="$dispatch('toggle-new-transaction')"
                />
            </div>
        </x-ib-toolbar>

        @livewire('portfolio-performance-chart', [
            'name' => 'portfolio-'.$portfolio->id,
            'portfolio' => $portfolio
        ])

        <div class="grid sm:grid-cols-5 gap-5">

            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap">{{ __('Market Gain/Loss') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_gain_dollars) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap">{{ __('Total Cost Basis') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_cost_basis) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap">{{ __('Total Market Value') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_market_value) }} </div>
            </x-card>
            
            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap">{{ __('Realized Gain/Loss') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->realized_gain_dollars) }} </div>
            </x-card>

            <x-card class="col-span-5 sm:col-span-1 bg-slate-100 dark:bg-base-200 rounded-lg">
                <div class="text-sm text-gray-400 whitespace-nowrap">{{ __('Dividends Earned') }}</div>
                <div class="font-black text-xl"> {{ Number::currency($metrics->total_dividends_earned) }} </div>
            </x-card>
                
        </div>

        <div class="mt-6 grid md:grid-cols-7 gap-5">

            <x-ib-card title="{{ __('Holdings') }}" class="md:col-span-4">

                @livewire('holdings-table', [
                    'portfolio' => $portfolio
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

            <x-ib-card title="{{ __('Recent activity') }}" class="md:col-span-3">
                
                @livewire('transactions-list', [
                    'portfolio' => $portfolio
                ])

            </x-ib-card>

            <x-ib-card title="{{ __('Top performers') }}" class="md:col-span-3">
                
                @foreach($portfolio->holdings->sortBy('market_gain_percent')->where('quantity', '>', 0)->take(5) as $holding)
                    <x-list-item no-separator :item="$holding">

                        @php
                            $gainPercent = (($holding->market_data?->market_value - $holding->average_cost_basis) / $holding->average_cost_basis) * 100;
                        @endphp

                        <x-slot:value class="flex items-center">

                            {{ $holding->symbol }}
                            
                            <x-badge class="{{ $gainPercent > 0 ? 'badge-success' : 'badge-error' }} ml-2 badge-sm" >
                                <x-slot:value>
                                    {{ Number::percentage($gainPercent) }} 
                                </x-slot:value>
                            </x-badge>
                            
                        </x-slot:value>
                        <x-slot:sub-value>
                            {{ $holding->market_data?->name }}
                        </x-slot:sub-value>
                    </x-list-item>
                @endforeach

            </x-ib-card>

        </div>
    </div>
</x-app-layout>