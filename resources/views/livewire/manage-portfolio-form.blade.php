<?php

use App\Models\Portfolio;
use Illuminate\Support\Collection;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    // props
    public ?Portfolio $portfolio;
    public Bool $hideCancel = false;

    #[Rule('required|min:5')]
    public String $title;

    #[Rule('sometimes|nullable')]
    public ?String $notes;

    #[Rule('sometimes|nullable|boolean')]
    public Bool $wishlist = false;

    public Bool $confirmingPortfolioDeletion = false;

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
        $this->portfolio->update($this->validate());
        // $this->portfolio->owner_id = auth()->user()->id;
        $this->portfolio->save();

        $this->success(__('Portfolio updated'), redirectTo: "/portfolio/{$this->portfolio->id}");
    }

    public function save()
    {
        $portfolio = (new Portfolio())->fill($this->validate());
        // $portfolio->owner_id = auth()->user()->id;
        $portfolio->save();

        $this->success(__('Portfolio created'), redirectTo: "/portfolio/{$portfolio->id}");
    }

    public function delete()
    {

        $this->portfolio->delete();

        $this->success(__('Portfolio deleted'), redirectTo: route('dashboard'));
    }
}; ?>

<div class="grid lg:grid-cols-4 gap-10">
    <x-ib-form wire:submit="{{ $portfolio ? 'update' : 'save' }}" class="col-span-3">
        <x-input label="{{ __('Title') }}" wire:model="title" required />

        <x-textarea label="{{ __('Notes') }}" wire:model="notes" rows="5" />

        <x-toggle label="{{ __('Wishlist') }}" wire:model="wishlist">
            <x-slot:hint>
                {{ __('Treat this portfolio as a "wishlist" (holdings will be excluded from realized gains, unrealized gains, and dividends)') }}
            </x-slot:hint>
        </x-toggle>

        <x-slot:actions>
            @if ($portfolio)
                <x-button 
                    wire:click="$toggle('confirmingPortfolioDeletion')" 
                    wire:loading.attr="disabled"
                    icon="o-trash"
                    class="btn btn-ghost btn-circle text-error" 
                    title="{{ __('Delete Portfolio') }}"
                />
            @endif

            @if (!$hideCancel)
                <x-button label="{{ __('Cancel') }}" link="/dashboard" />
            @endif
            <x-button label="{{ $portfolio ? __('Update') : __('Create') }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
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
            <x-button class="btn-outline" wire:click="$toggle('confirmingPortfolioDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-button class="ms-3 btn-error text-white" wire:click="delete" wire:loading.attr="disabled">
                {{ __('Delete Portfolio') }}
            </x-button>
        </x-slot>
    </x-confirmation-modal>
</div>