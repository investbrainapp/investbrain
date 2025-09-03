<x-layouts.app>
    <div x-data>  

        <x-ib-alpine-modal 
            key="create-transaction"
            title="{{ __('Create Transaction') }}"
        >
            @livewire('manage-transaction-form')

        </x-ib-alpine-modal>

        <x-ib-toolbar title="{{ __('All Transactions') }}">

            <x-ib-flex-spacer />
            
            <div>
                <x-button 
                    label="{{ __('Create Transaction') }}" 
                    class="btn-sm btn-primary whitespace-nowrap " 
                    @click="$dispatch('toggle-create-transaction')"
                />
            </div>
        </x-ib-toolbar>

        @livewire('transactions-table')

    </div>
</x-layouts.app>
