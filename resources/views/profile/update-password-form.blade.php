<?php

namespace Laravel\Jetstream\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * The component's state.
     *
     * @var array
     */
    public $state = [
        'current_password' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    /**
     * Update the user's password.
     *
     * @return void
     */
    public function updatePassword(UpdatesUserPasswords $updater)
    {
        $this->resetErrorBag();

        $updater->update(Auth::user(), $this->state);

        if (request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Auth::getDefaultDriver() => Auth::user()->getAuthPassword(),
            ]);
        }

        $this->state = [
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ];

        $this->dispatch('saved');
    }

    /**
     * Get the current user of the application.
     *
     * @return mixed
     */
    public function getUserProperty()
    {
        return Auth::user();
    }
} ?>

<x-forms.form-section submit="updatePassword">
    <x-slot name="title">
        {{ __('Update Password') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Ensure your account is using a long, random password to stay secure.') }}
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            
            <x-ui.input id="current_password" label="{{ __('Current Password') }}" type="password" class="mt-1 block w-full" wire:model="state.current_password" error-field="current_password" autocomplete="current-password" />
            
        </div>

        <div class="col-span-6 sm:col-span-4">
            
            <x-ui.input id="password" label="{{ __('New Password') }}"  type="password" class="mt-1 block w-full" wire:model="state.password" error-field="password" autocomplete="new-password" />
            
        </div>

        <div class="col-span-6 sm:col-span-4">
            
            <x-ui.input id="password_confirmation" label="{{ __('Confirm Password') }}" type="password" class="mt-1 block w-full" wire:model="state.password_confirmation" error-field="password_confirmation" autocomplete="new-password" />
            
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-forms.action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-forms.action-message>

        <x-ui.button type="submit">
            {{ __('Save') }}
        </x-ui.button>
    </x-slot>
</x-forms.form-section>
