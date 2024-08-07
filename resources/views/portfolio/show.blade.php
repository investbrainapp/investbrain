<x-app-layout>
    <div x-data>

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
            <x-badge value="{{ __('Wishlist') }}" class="badge-primary mr-3" />
            @endif

            <x-button 
                title="{{ __('Edit Portfolio') }}" 
                icon="o-pencil" 
                class="btn-circle btn-ghost btn-sm text-secondary" 
                @click="$dispatch('toggle-manage-portfolio')"
            />
        </x-ib-toolbar>

        <x-card class="bg-slate-100 dark:bg-base-200 rounded-lg mb-6">

            @livewire('portfolio-performance-chart', [
                'name' => 'portfolio-'.$portfolio->id,
                'portfolio' => $portfolio
            ])

        </x-card>

        <div class="grid md:grid-cols-5 gap-5">
            @livewire('portfolio-performance-card')
            {{-- <x-stat
                class="bg-slate-100 dark:bg-base-200"
                title="Market Gain/Loss"
                value="22.124"
                icon="o-arrow-trending-up"
            /> --}}
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

            <x-ib-card title="All portfolio holdings" class="md:col-span-4">
            
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
        
    </div>
</x-app-layout>