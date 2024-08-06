<x-app-layout>
    <div>  

        <x-ib-toolbar title="Create Portfolio" />

        @livewire('manage-portfolio-form', ['submit' => 'save'])
    </div>
</x-app-layout>
