<x-app-layout>
    <div x-data>  

        <x-ib-modal 
            key="create-transaction"
            title="{{ __('Create Transaction') }}"
        >
            @livewire('manage-transaction-form')

        </x-ib-modal>

        <x-ib-toolbar title="{{ __('All Transactions') }}">

            <x-ib-flex-spacer />
            
            <div>
                <x-button 
                    label="{{ __('Create Transaction') }}" 
                    class="btn-sm btn-primary" 
                    @click="$dispatch('toggle-create-transaction')"
                />
            </div>
        </x-ib-toolbar>

        @livewire('transactions-table')

    </div>
</x-app-layout>
