<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class CommonLayout extends Component
{
    public function __construct(

        // Slots
        public mixed $body = null,
    ) { }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.common');
    }
}
