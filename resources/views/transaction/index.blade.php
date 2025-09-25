<x-layouts.app>
    <div x-data>  

        <x-ui.modal 
            key="create-transaction"
            title="{{ __('Create Transaction') }}"
        >
            @livewire('manage-transaction-form')

        </x-ui.modal>

        <x-ui.toolbar title="{{ __('All Transactions') }}">

            <x-ui.flex-spacer />
            
            <div>
                <x-ui.button 
                    label="{{ __('Create Transaction') }}" 
                    class="btn-sm btn-primary whitespace-nowrap " 
                    @click="$dispatch('toggle-create-transaction')"
                />
            </div>
        </x-ui.toolbar>

        @livewire('datatables.transactions-table')
    </div>
</x-layouts.app>
