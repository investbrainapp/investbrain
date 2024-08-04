<?php

use App\Models\Portfolio;
use Livewire\Attributes\{Title, Rule};
use Livewire\Volt\Component;

new class extends Component {

    public bool $showDrawer2 = false;

    public ?Portfolio $portfolio;
    
}; ?>
<div>  

    <x-ib-toolbar title="Create Portfolio" />

    <livewire:portfolio.manage-portfolio-form submit="save" />
</div>
