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
    <button
        type="button"
        class="badge badge-secondary badge-outline select-none cursor-pointer"
        title="{{ __('Owner').': '.$portfolio->owner->name }}"
        @click="$dispatch('toggle-portfolio-access')"
    >
        {{ $portfolio->owner->name }}
    </button>

    <x-ui.modal
        key="portfolio-access"
        title="{{ __('Portfolio shared with you') }}"
    >
        <div class="mt-2 text-sm text-secondary-content space-y-1">
            <div class="font-bold text-base-content">{{ $portfolio->title }}</div>
            <div>{{ __('Shared by :name', ['name' => $portfolio->owner->name]) }}</div>
            <div>{{ __('Access') }}: {{ auth()->user()->can('fullAccess', $portfolio) ? __('Full access') : __('Read only') }}</div>
        </div>

        <x-ui.section-border class="my-3" />

        <div class="flex justify-end gap-3">
            <x-ui.button
                @click="$dispatch('toggle-portfolio-access'); $wire.set('confirmingLeavePortfolio', true)"
                wire:loading.attr="disabled"
                class="btn text-error"
                title="{{ __('Leave Portfolio') }}"
                label="{{ __('Leave Portfolio') }}"
            />
        </div>
    </x-ui.modal>

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
