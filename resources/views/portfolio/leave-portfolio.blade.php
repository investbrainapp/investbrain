<?php

use App\Models\Portfolio;
use App\Traits\Toast;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;

    // props
    public Portfolio $portfolio;

    public bool $confirmingLeavePortfolio = false;

    // methods
    public function leave()
    {
        $this->authorize('readOnly', $this->portfolio);

        abort_if(auth()->user()->can('owner', $this->portfolio), 403);

        $this->portfolio->unShare(auth()->user()->id);

        $this->success(__('You have left the portfolio'), redirectTo: route('dashboard'));
    }
}; ?>

<div class="mr-3">
    <div class="dropdown dropdown-hover">
        <div
            tabindex="0"
            role="button"
            class="badge badge-secondary badge-outline select-none cursor-pointer"
            title="{{ __('Owner').': '.$portfolio->owner->name }}"
        >
            {{ $portfolio->owner->name }}
        </div>

        <ul tabindex="0" class="dropdown-content menu p-2 shadow-lg z-50 bg-base-100 rounded-box w-auto min-w-max">
            <x-ui.menu-item
                title="{{ __('Leave Portfolio') }}"
                icon="o-arrow-right-start-on-rectangle"
                class="text-error"
                wire:click="$toggle('confirmingLeavePortfolio')"
            />
        </ul>
    </div>

    <x-ui.confirmation-modal wire:model.live="confirmingLeavePortfolio">
        <x-slot name="title">
            {{ __('Leave Portfolio') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you want to leave this portfolio? You will lose access until the owner shares it with you again.') }}
        </x-slot>

        <x-slot name="footer">
            <x-ui.button class="btn-outline" wire:click="$toggle('confirmingLeavePortfolio')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-ui.button>

            <x-ui.button class="ms-3 btn-error text-white" wire:click="leave" wire:loading.attr="disabled">
                {{ __('Leave Portfolio') }}
            </x-ui.button>
        </x-slot>
    </x-ui.confirmation-modal>
</div>
