<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render()
    {
        return <<<'HTML'
                    <x-main-layout>
                        <x-slot:body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200" x-data>

                            <div>

                                <x-partials.nav-bar />

                                <x-main with-nav full-width>

                                    <x-slot:sidebar drawer="main-drawer" class="bg-base-100 lg:bg-inherit">
                            
                                        <x-partials.side-bar />

                                    </x-slot:sidebar>

                                    <x-slot:content>

                                        {{ $slot }}
                                    </x-slot:content>

                                </x-main>
                            
                                @if(session('toast'))
                                    <script lang="text/javascript">
                                        window.addEventListener('DOMContentLoaded', function () {
                                            window.toast(JSON.parse(@json(session('toast'))))
                                        });
                                    </script>
                                @endif
                                <x-toast />
                            </div>
                    
                        </x-slot:body>
                    </x-main-layout>
                HTML;
    }
}
