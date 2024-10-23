<?php

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;

new class extends Component {

    // props
    public Portfolio $portfolio;
    public User $user;

    #[Rule('required|string|confirmed')]
    public string $password;

    #[Rule('required|string')]
    public string $password_confirmation;

    // methods
    public function updatePassword()
    {
        
        $this->validate();

        $this->user->password = Hash::make($this->password);
        $this->user->save();

        Auth::login($this->user, true);

        return redirect(route('portfolio.show', ['portfolio' => $this->portfolio->id]));
    }

}; ?>

<x-form wire:submit="updatePassword" class="">

    <div class="mt-2">
        
        <x-input wire:model="password" label="{{ __('Password') }}" class="block mt-1 w-full" type="password" required autocomplete="new-password" autofocus />
    </div>

    <div class="mt-2">
        
        <x-input wire:model="password_confirmation" label="{{ __('Confirm Password') }}" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
    </div>

    <div class="flex items-center justify-end mt-2">

        <x-button class="btn-primary" type="submit">
            {{ __('Create Password') }}
        </x-button>
    </div>
</x-form>