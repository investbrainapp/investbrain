<x-forms.form-section submit="updatePassword">
    <x-slot name="title">
        {{ __('Update Password') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Ensure your account is using a long, random password to stay secure.') }}
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            
            <x-input id="current_password" label="{{ __('Current Password') }}" type="password" class="mt-1 block w-full" wire:model="state.current_password" error-field="current_password" autocomplete="current-password" />
            
        </div>

        <div class="col-span-6 sm:col-span-4">
            
            <x-input id="password" label="{{ __('New Password') }}"  type="password" class="mt-1 block w-full" wire:model="state.password" error-field="password" autocomplete="new-password" />
            
        </div>

        <div class="col-span-6 sm:col-span-4">
            
            <x-input id="password_confirmation" label="{{ __('Confirm Password') }}" type="password" class="mt-1 block w-full" wire:model="state.password_confirmation" error-field="password_confirmation" autocomplete="new-password" />
            
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-forms.action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-forms.action-message>

        <x-ib-button type="submit">
            {{ __('Save') }}
        </x-ib-button>
    </x-slot>
</x-forms.form-section>
