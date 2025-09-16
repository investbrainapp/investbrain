<x-layouts.app>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        @livewire('import-portfolios-field')

        <x-ui.section-border hide-on-mobile />
    </div>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-forms.action-section>
            <x-slot name="title">
                {{ __('Export') }}
            </x-slot>
        
            <x-slot name="description">
                {{ __('Download all of your portfolios and transactions.') }}
            </x-slot>
        
            <x-slot name="content">
                
                <div class="col-span-6 sm:col-span-4">
                    @livewire('export-portfolios-button')
                    
                </div>
        
            </x-slot>
    
        </x-forms.form-section>


    </div>
 
</x-layouts.app>