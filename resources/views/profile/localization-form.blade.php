<?php

use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;

new class extends Component
{
    // props

    public Collection $currencies;

    public string $display_currency;

    public ?string $locale;

    public ?User $user;

    // methods
    public function rules()
    {
        return [
            'locale' => ['required', 'in:'.implode(',', Arr::pluck(config('app.available_locales'), 'locale'))],
            'display_currency' => ['required', 'exists:currencies,currency'],
        ];
    }

    public function mount()
    {
        $this->currencies = Currency::get();
        $this->display_currency = auth()->user()->getCurrency();
        $this->locale = auth()->user()->getLocale();
        $this->user = auth()->user();
    }

    public function updateProfileInformation()
    {
        $this->resetErrorBag();

        $this->validate();

        $this->user->options = array_merge($this->user->options ?? [], [
            'locale' => $this->locale,
            'display_currency' => $this->display_currency,
        ]);

        $this->user->save();

        cache()->tags(['metrics-'.$this->user->id])->flush();

        $this->dispatch('saved');

        //$this->js('window.location.reload();');
    }
}; ?>
<x-forms.form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Locale Options') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Adjust localization options for your preferred region.') }}
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-select 
                label="{{ __('Locale') }}"
                class="select block mt-1 w-full"
                :options="config('app.available_locales')"
                option-value="locale"
                option-label="label"
                placeholder="Choose a locale"
                wire:model="locale"
                id="locale"
            />
            
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-select 
                label="{{ __('Display Currency') }}"
                class="select block mt-1 w-full"
                :options="$currencies"
                option-value="currency"
                option-label="label"
                placeholder="Choose a display currency"
                wire:model="display_currency"
                id="display_currency"
            />
            
        </div>

    </x-slot>

    <x-slot name="actions">
        <x-forms.action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-forms.action-message>

        <x-button type="submit">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-forms.form-section>
