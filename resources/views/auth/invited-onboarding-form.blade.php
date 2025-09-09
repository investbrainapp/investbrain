<?php

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public Portfolio $portfolio;

    public User $user;

    #[Rule('required|string')]
    public string $name;

    #[Rule('required|string|min:8|confirmed')]
    public string $password;

    #[Rule('required|string')]
    public string $password_confirmation;

    // methods
    public function mount()
    {
        $this->name = $this->user->name;
    }

    public function updateUserInformation()
    {

        $this->validate();

        $this->user->name = $this->name;
        $this->user->password = Hash::make($this->password);
        $this->user->email_verified_at = now();
        $this->user->save();

        Auth::login($this->user, true);

        return redirect(route('portfolio.show', ['portfolio' => $this->portfolio->id]));
    }
}; ?>

<x-form wire:submit="updateUserInformation" class="">

    <div class="mt-2">
        
        <x-ib-input wire:model="name" label="{{ __('Name') }}" class="block mt-1 w-full" required autofocus />
    </div>

    <div class="mt-2">
        
        <x-ib-input wire:model="password" label="{{ __('Password') }}" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
    </div>

    <div class="mt-2">
        
        <x-ib-input wire:model="password_confirmation" label="{{ __('Confirm Password') }}" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
    </div>

    <div class="flex items-center justify-end mt-2">

        <x-ib-button class="btn-primary" type="submit">
            {{ __('Get Started') }}
        </x-ib-button>
    </div>
</x-form>