<?php

use App\Models\Portfolio;
use App\Traits\Toast;
use App\Traits\WithTrimStrings;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;
    use WithTrimStrings;

    // props
    public ?Portfolio $portfolio;

    public bool $hideCancel = false;

    #[Rule('required|min:5')]
    public string $title;

    #[Rule('sometimes|nullable')]
    public ?string $notes;

    #[Rule('sometimes|nullable|boolean')]
    public bool $wishlist = false;

    public bool $confirmingPortfolioDeletion = false;

    // methods
    public function mount()
    {
        if (isset($this->portfolio)) {

            $this->title = $this->portfolio->title;
            $this->notes = $this->portfolio->notes;
            $this->wishlist = $this->portfolio->wishlist;
        }
    }

    public function update()
    {
        $this->authorize('fullAccess', $this->portfolio);

        $this->portfolio->update($this->validate());
        $this->portfolio->save();

        $this->success(__('Portfolio updated'), redirectTo: "/portfolio/{$this->portfolio->id}");
    }

    public function save()
    {
        $portfolio = (new Portfolio)->fill($this->validate());

        $portfolio->save();

        $this->success(__('Portfolio created'), redirectTo: "/portfolio/{$portfolio->id}");
    }

    public function delete()
    {
        $this->authorize('fullAccess', $this->portfolio);

        $this->portfolio->delete();

        $this->success(__('Portfolio deleted'), redirectTo: route('dashboard'));
    }
}; ?>

<div class="w-full md:w-3/4">

    <x-ib-form wire:submit="{{ $portfolio ? 'update' : 'save' }}" >
        <x-ib-input label="{{ __('Title') }}" wire:model="title" required />

        <x-ib-textarea class="mt-1" label="{{ __('Notes') }}" wire:model="notes" rows="4" />

        @if (isset($this->portfolio))
        @livewire('share-portfolio-form', ['portfolio' => $portfolio])
        @endif

        <x-ib-toggle label="{{ __('Wishlist') }}" wire:model="wishlist" >
            <x-slot:hint>
                {{ __('Treat this portfolio as a "wishlist" (holdings will be excluded from realized gains, unrealized gains, and dividends)') }}
            </x-slot:hint>
        </x-ib-toggle>

        <x-slot:actions>
            @if ($portfolio)
                <x-ib-button 
                    wire:click="$toggle('confirmingPortfolioDeletion')" 
                    wire:loading.attr="disabled"
                    class="btn  text-error" 
                    title="{{ __('Delete Portfolio') }}"
                    label="{{ __('Delete Portfolio') }}"
                />
            @endif

            @if (!$hideCancel)
                <x-ib-button label="{{ __('Cancel') }}" link="/dashboard" />
            @endif
            <x-ib-button label="{{ $portfolio ? __('Update') : __('Create') }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-ib-form>

    <x-confirmation-modal wire:model.live="confirmingPortfolioDeletion">
        <x-slot name="title">
            {{ __('Delete Portfolio') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you want to delete this portfolio? Once a portfolio is deleted, all of its holdings and other data will be permanently deleted.') }}
        </x-slot>

        <x-slot name="footer">
            <x-ib-button class="btn-outline" wire:click="$toggle('confirmingPortfolioDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-ib-button class="ms-3 btn-error text-white" wire:click="delete" wire:loading.attr="disabled">
                {{ __('Delete Portfolio') }}
            </x-ib-button>
        </x-slot>
    </x-confirmation-modal>
</div>