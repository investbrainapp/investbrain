<?php

use App\Models\Holding;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // props
    public Holding $holding;

    public bool $reinvest_dividends = false;

    // methods
    public function rules()
    {

        return [
            'reinvest_dividends' => ['required', 'boolean'],
        ];
    }

    public function mount()
    {

        $this->reinvest_dividends = $this->holding?->reinvest_dividends ?? false;
    }

    public function save()
    {
        $this->holding->update($this->validate());

        $this->success(__('Holding options saved'));

        $this->dispatch('toggle-holding-options');
    }
}; ?>

<div class="" x-data="{ }"> {{-- grid lg:grid-cols-4 gap-10 --}}
    <x-ib-form wire:submit="save" class=""> {{-- col-span-3 --}}

        <x-toggle 
            label="{{ __('Reinvest Dividends') }}" 
            wire:model="reinvest_dividends" 
            right 
            hint="{{ __('Automatically generate buy transactions for any dividends earned.') }}"
        />

        <x-slot:actions>

            <x-ib-button 
                label="{{ __('Save') }}" 
                type="submit" 
                icon="o-paper-airplane" 
                class="btn-primary" 
                spinner="save"
            />
        </x-slot:actions>
    </x-ib-form>

</div>