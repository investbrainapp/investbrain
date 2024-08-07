<x-app-layout>

    <x-card class="bg-slate-100 dark:bg-base-200 rounded-lg mb-6">

        @livewire('portfolio-performance-chart', [
            'name' => 'dashboard'
        ])
        
    </x-card>

    <div class="grid md:grid-cols-5 gap-5">
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Market Gain/Loss"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Total Cost Basis"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Total Market Value"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Realized Gain/Loss"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        <x-stat
            class="bg-slate-100 dark:bg-base-200"
            title="Dividends Earned"
            value="22.124"
            icon="o-arrow-trending-up"
        />
        
    </div>

    <div class="mt-6 grid md:grid-cols-7 gap-5">

        <x-ib-card title="My portfolios" class="md:col-span-4">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>

        <x-ib-card title="Top performers" class="md:col-span-3">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>
        
        <x-ib-card title="Top headlines" class="md:col-span-3">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>

        <x-ib-card title="Recent activity" class="md:col-span-4">
         
            @php
                $users = App\Models\User::take(3)->get();
            @endphp
            
            @foreach($users as $user)
                <x-list-item no-separator :item="$user" avatar="profile_photo_url" link="/docs/installation" />
            @endforeach

        </x-ib-card>

    </div>
    
</x-app-layout>