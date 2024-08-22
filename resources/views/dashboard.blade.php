<x-app-layout>

    @livewire('portfolio-performance-chart', [
        'name' => 'dashboard'
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

        <x-ib-card title="{{ __('My portfolios') }}" class="md:col-span-4">

            @if ($user->portfolios->isEmpty())
                <div class="flex justify-center items-center h-[100px] mb-8">
                    <x-button label="{{ __('Create your first portfolio!') }}" class="btn-primary" link="{{ route('portfolio.create') }}" />
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

        @if (!$user->portfolios->isEmpty())
        <x-ib-card title="{{ __('Top performers') }}" class="md:col-span-3">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>
        @endif
        
        {{-- @if (!$user->portfolios->isEmpty())
        <x-ib-card title="{{ __('Top headlines') }}" class="md:col-span-3">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>
        @endif --}}

        @if (!$user->portfolios->isEmpty())
        <x-ib-card title="{{ __('Recent activity') }}" class="md:col-span-4">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>
        @endif

    </div>
    
</x-app-layout>