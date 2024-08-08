<x-app-layout>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-forms.form-section submit="updateProfileInformation">
                <x-slot name="title">
                    {{ __('Import') }}
                </x-slot>
            
                <x-slot name="description">
                    {{ __('Upload or recover your Investbrain portfolio and holdings.') }}
                </x-slot>
            
                <x-slot name="form">
                    
                    <!-- Name -->
                    <div class="col-span-6 sm:col-span-4">
                        @livewire('import-portfolios-field')
                    </div>
            
                </x-slot>
            
                <x-slot name="actions">
              
                    <x-button type="submit">
                        {{ __('Import') }}
                    </x-button>
                </x-slot>
            </x-forms.form-section>
            
            <x-section-border />
        </div>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-forms.action-section submit="updateProfileInformation">
                <x-slot name="title">
                    {{ __('Export') }}
                </x-slot>
            
                <x-slot name="description">
                    {{ __('Download all of your portfolios and transactions.') }}
                </x-slot>
            
                <x-slot name="content">
                    
                    <!-- Name -->
                    <div class="col-span-6 sm:col-span-4">
                        @livewire('export-portfolios-button')
                        

                    </div>
            
                </x-slot>
        
            </x-forms.form-section>


        </div>
 
</x-app-layout>