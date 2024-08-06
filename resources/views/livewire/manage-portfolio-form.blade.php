<?php

use App\Models\Portfolio;
use Illuminate\Support\Collection;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $submit;
    public ?Portfolio $portfolio;
    public Bool $hideCancel = false;

    #[Rule('required|min:5')]
    public string $title;

    #[Rule('sometimes|nullable')]
    public ?string $notes;

    #[Rule('sometimes|boolean')]
    public ?bool $wishlist;

    public function mount() {

        if (isset($this->portfolio)) {

            $this->title = $this->portfolio->title;
            $this->notes = $this->portfolio->notes;
            $this->wishlist = $this->portfolio->wishlist;
        }
    }

    public function categories(): Collection
    {
        return Category::all();
    }

    public function update()
    {
        $this->portfolio->update($this->validate());
        // $this->portfolio->owner_id = auth()->user()->id;
        $this->portfolio->save();

        $this->success('Portfolio updated', redirectTo: "/portfolio/{$this->portfolio->id}");
    }

    public function save()
    {

        // // get stats
        // $key = 'portfolio-metrics-' . $portfolio->id;
        // $metrics = cache()->remember($key, 60, function () use ($portfolio) {
        //     return Holding::where(['portfolio_id' => $portfolio->id])
        //         ->getPortfolioMetrics()
        //         ->first();
        // });

        // return view('pages.portfolios.show', [
        //     'portfolio' => $portfolio,
        //     'metrics' => $metrics
        // ]);

        $portfolio = (new Portfolio())->fill($this->validate());
        // $portfolio->owner_id = auth()->user()->id;
        $portfolio->save();

        $this->success('Portfolio created', redirectTo: "/portfolio/{$portfolio->id}");
    }

    public function with(): array
    {
        return [
        //     'categories' => $this->categories()
        ];
    }
}; ?>

<div class="grid lg:grid-cols-4 gap-10">
    <x-form wire:submit="{{ $submit }}" class="col-span-3">
        <x-input label="Title" wire:model="title" required />

        {{-- <x-select label="Category" wire:model="category_id" placeholder="Select a category" :options="$categories" /> --}}

        <x-textarea label="Notes" wire:model="notes" rows="5" />

        <x-toggle label="Wishlist" wire:model="wishlist" />

        <x-slot:actions>
            @if (!$hideCancel)
            <x-button label="Cancel" link="{{ url()->previous() }}" />
            @endif
            <x-button label="{{ $submit == 'save' ? 'Create' : 'Update' }}" type="submit" icon="o-paper-airplane" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>