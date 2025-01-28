<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;

class GuestLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render()
    {
        return <<<'HTML'
                    <x-main-layout>
                        <x-slot:body class="font-sans text-gray-900 dark:text-gray-100 antialiased">

                            {{ $slot }}

                            <x-theme-toggle class="hidden" darkTheme="business" lightTheme="corporate"/>

                        </x-slot:body>
                    </x-main-layout>
                HTML;
    }
}
