@use('App\Models\Currency')

<x-layouts.app>
    <div x-data>

        <x-ui.modal 
            key="create-transaction"
            title="{{ __('Create Transaction') }}"
        >
            @livewire('manage-transaction-form', [
                'portfolio' => $portfolio, 
            ])

        </x-ui.modal>

        <x-ui.drawer 
            key="manage-portfolio"
            title="{{ __('Manage Portfolio') }}"
        >
            @livewire('manage-portfolio-form', [
                'portfolio' => $portfolio, 
                'hideCancel' => true
            ])

        </x-ui.drawer>

        <x-ui.toolbar :title="$portfolio->title">

            @if($portfolio->wishlist)
            <x-ui.badge value="{{ __('Wishlist') }}" title="{{ __('Wishlist') }}" class="badge-secondary badge-outline mr-3" />
            @endif

            @if(auth()->user()->id !== $portfolio->owner_id)
            <x-ui.badge value="{{ $portfolio->owner->name }}" title="{{ __('Owner').': '.$portfolio->owner->name }}" class="badge-secondary badge-outline mr-3" />
            @endif

            @can('fullAccess', $portfolio)
            <x-ui.button 
                title="{{ __('Manage Portfolio') }}" 
                icon="o-pencil" 
                class="btn-circle btn-ghost btn-sm text-secondary" 
                @click="$dispatch('toggle-manage-portfolio')"
            />
            @else
            <x-ui.icon name="o-eye" class="text-secondary w-4" title="{{ __('Read only') }}" />
            @endcan

            <x-ui.flex-spacer />
            
            @can('fullAccess', $portfolio)
            <div>
                <x-ui.button 
                    label="{{ __('Create Transaction') }}" 
                    class="btn-sm btn-primary whitespace-nowrap" 
                    @click="$dispatch('toggle-create-transaction')"
                />
            </div>
            @endcan
        </x-ui.toolbar>

        @livewire('portfolio-performance-chart', [
            'name' => 'portfolio-'.$portfolio->id,
            'portfolio' => $portfolio
        ])

        <div class="grid sm:grid-cols-5 gap-5">

            <x-ui.card dense="true" sub-title="{{ __('Market Gain/Loss') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_market_gain_dollars', 0)) }} </div>
            </x-ui.card>
            
            <x-ui.card dense="true" sub-title="{{ __('Total Cost Basis') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_cost_basis', 0)) }} </div>
            </x-ui.card>
            
            <x-ui.card dense="true" sub-title="{{ __('Total Market Value') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_market_value', 0)) }} </div>
            </x-ui.card>
            
            <x-ui.card dense="true" sub-title="{{ __('Realized Gain/Loss') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('realized_gain_dollars', 0)) }} </div>
            </x-ui.card>

            <x-ui.card dense="true" sub-title="{{ __('Dividends Earned') }}" class="col-span-5 sm:col-span-1">
                <div class="font-black text-xl"> {{ Number::currency($metrics->get('total_dividends_earned', 0)) }} </div>
            </x-ui.card>
                
        </div>

        <div class="mt-6 grid md:grid-cols-7 gap-5">

            <x-ui.card title="{{ __('Holdings') }}" class="md:col-span-4 overflow-scroll">

                @if($portfolio->holdings->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('Nothing to show here yet') }}
                    </div>

                @else

                    @livewire('holdings-table', [
                        'portfolio' => $portfolio
                    ])

                @endif
            </x-ui.card>

            <x-ui.card title="{{ __('Recent activity') }}" class="md:col-span-3">

                @if($portfolio->transactions->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('Nothing to show here yet') }}
                    </div>
                
                @endif

                @livewire('transactions-list', [
                    'portfolio' => $portfolio,
                    'transactions' => $portfolio->transactions
                ])

            </x-ui.card>

            <x-ui.card title="{{ __('Top performers') }}" class="md:col-span-3">

                @if($portfolio->holdings->isEmpty())
                    <div class="flex justify-center items-center h-full pb-10 text-secondary">

                        {{ __('Nothing to show here yet') }}
                    </div>

                @endif

                @livewire('top-performers-list', [
                    'holdings' => $portfolio->holdings
                ])

            </x-ui.card>

            {{-- <x-ui.card title="{{ __('Top headlines') }}" class="md:col-span-3">
            
                @php
                    $users = App\Models\User::take(3)->get();
                @endphp
                
                @foreach($users as $user)
                    <x-ui.list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
                @endforeach

            </x-ui.card> --}}

            @if(config('services.ai_chat_enabled'))
            @livewire('ui.ai-chat-window', [
                'chatable' => $portfolio,
                'suggested_prompts' => [
                    [
                        'text' => 'Which holding is most successful?',
                        'value' => 'Which holding is most successful in this portfolio?',
                    ],
                    [
                        'text' => 'Should I diversify more?',
                        'value' => 'Is my portfolio diverse enough?',
                    ]
                ],
                'system_prompt' => "
                        You are an investment portfolio assistant providing advice to an investor.  Use the following information to provide relevant recommendations.  Use the words 'likely' or 'may' instead of concrete statements (except for obvious statements of fact or common sense):

                        The investor has the following holdings in this portfolio:
                        
                        {$formattedHoldings}

                        This data is current as of today's date: " . now()->toDateString() . ". Based on the current market data, quantity owned, and average cost basis, you can determine the performance of any holding.

                        Below is the question from the investor. Considering these facts, provide a concise response to the following question (give a direct response). Limit your response to no more than 75 words and consider using a common decision framework. Use github style markdown for any formatting:"
            ])
            @endif

        </div>
    </div>
</x-layouts.app>