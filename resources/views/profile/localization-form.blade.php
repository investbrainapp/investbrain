<x-forms.form-section submit="updatePassword">
    <x-slot name="title">
        {{ __('Locale Settings') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Adjust currency display settings to your region.') }}
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            
            <x-input id="password_confirmation" label="{{ __('Currency') }}" type="password" class="mt-1 block w-full" wire:model="state.password_confirmation" error-field="password_confirmation" autocomplete="new-password" />
            
        </div>

    </x-slot>

    <x-slot name="actions">
        <x-forms.action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-forms.action-message>

        <x-button type="submit">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-forms.form-section>
