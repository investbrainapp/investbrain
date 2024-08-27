<x-app-layout>
    <div>  

        <x-ib-toolbar title="{{ __('All Transactions') }}" />

        @livewire('transactions-table')

    </div>
</x-app-layout>
