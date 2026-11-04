<?php

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    // props
    public Portfolio $portfolio;

    public User $user;

    /**
     * When the signed invite link expires, as a Unix timestamp.
     */
    #[Locked]
    public int $expiresAt;

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
        // the controller checks the signed link once, but a saved copy of this form can be submitted later,
        // so check again that the invite is still open: unexpired, and no password set yet
        abort_if(now()->timestamp > $this->expiresAt || ! is_null($this->user->password), 403);

        $this->validate();

        $this->user->name = $this->name;
        $this->user->password = Hash::make($this->password);
        $this->user->email_verified_at = now();
        $this->user->save();

        Auth::login($this->user, true);

        return redirect(route('portfolio.show', ['portfolio' => $this->portfolio->id]));
    }
}; ?>

<x-ui.form wire:submit="updateUserInformation" class="">

    <div class="mt-2">
        
        <x-ui.input wire:model="name" label="{{ __('Name') }}" class="block mt-1 w-full" required autofocus />
    </div>

    <div class="mt-2">
        
        <x-ui.input wire:model="password" label="{{ __('Password') }}" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
    </div>

    <div class="mt-2">
        
        <x-ui.input wire:model="password_confirmation" label="{{ __('Confirm Password') }}" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
    </div>

    <div class="flex items-center justify-end mt-2">

        <x-ui.button class="btn-primary" type="submit">
            {{ __('Get Started') }}
        </x-ui.button>
    </div>
</x-ui.form>