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
        :title="$portfolio->title"
        box-class="max-w-md"
    >
        <div class="space-y-1 text-sm">
            <p>{{ __('Shared by :name', ['name' => $portfolio->owner->name]) }}</p>
            <p>{{ __('Access') }}: {{ auth()->user()->can('fullAccess', $portfolio) ? __('Full access') : __('Read only') }}</p>
        </div>

        <div class="flex justify-end mt-6">
            <x-ui.button
                class="btn-ghost text-error"
                icon="o-arrow-right-start-on-rectangle"
                label="{{ __('Leave Portfolio') }}"
                @click="$dispatch('toggle-portfolio-access'); $wire.set('confirmingLeavePortfolio', true)"
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
