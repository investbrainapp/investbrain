<x-app-layout>
    <div>  

        <x-ib-toolbar>
            <x-slot:title>
                <a href="{{ route('portfolio.show', ['portfolio' => $portfolio->id]) }}" title="{{ __('Portfolio') }}">
                    {{ $portfolio->title }}
                </a> » <span title="{{ __('Holding') }}">{{ $market_data->symbol }}</span>
            </x-slot:title>
        </x-ib-toolbar>

        <div class="mt-6 grid md:grid-cols-9 gap-5">

            <x-ib-card class="md:col-span-5">
                <x-slot:title class="pb-2">

                    {{ $market_data->symbol }} 
                    <span class="text-sm"> {{ $market_data->name }} </span>
                </x-slot:title>

                <p class="font-bold	text-2xl pb-2">
                    {{ Number::currency($market_data->market_value) }} 
                    
                    @if ($holding->average_cost_basis)
                    
                        @php
                            $isUp = $holding->average_cost_basis <= $market_data->market_value;
                            $percent = ($market_data->market_value - $holding->average_cost_basis) / $holding->average_cost_basis
                        @endphp

                        <span class="text-base font-normal" style="color: {{ $isUp ? 'rgb(0, 200, 0)' : 'rgb(255, 20, 0)' }};">
                            {!! $isUp ?  '&#9650;' :'&#9660;' !!}
                            {{ Number::percentage(
                                $percent,
                                $percent < 1 ? 2 : 1
                            ) }}
                        </span>
                    @endif
                   
                </p>

                <p>
                    <span class="font-bold">{{ __('Quantity Owned') }}: </span>
                    {{ $holding->quantity }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Average Cost Basis') }}: </span>
                    {{ Number::currency($holding->average_cost_basis) }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Total Cost Basis') }}: </span>
                    {{ Number::currency($holding->total_cost_basis) }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Realized Gain/Loss') }}: </span>
                    {{ Number::currency($holding->realized_gain_dollars) }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('Dividends Earned') }}: </span>
                    {{ Number::currency($holding->dividends_earned) }} 
                </p>

                <p>
                    <span class="font-bold">{{ __('52 week') }}: </span>

                    <x-fifty-two-week-range 
                        :low="$market_data->fifty_two_week_low" 
                        :high="$market_data->fifty_two_week_high" 
                        :current="$market_data->market_value"
                    />
                    
                </p>

                <p class="pt-2 text-sm">
                    {{ __('Market Data Age') }}: 
                    {{ \Carbon\Carbon::parse($market_data->updated_at)->diffForHumans() }}
                </p>

            </x-ib-card>

            <x-ib-card title="{{ __('Fundamentals') }}" class="md:col-span-4">

                

            </x-ib-card>

            <x-ib-card title="{{ __('Recent activity') }}" class="md:col-span-3">
                
                

            </x-ib-card>

            <x-ib-card title="{{ __('Dividends') }}" class="md:col-span-3">

                

            </x-ib-card>

            <x-ib-card title="{{ __('Splits') }}" class="md:col-span-3">

                

            </x-ib-card>

        </div>

    </div>
</x-app-layout>
