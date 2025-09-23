<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-base-200">
    <head>
        @include('components.partials.head')
    </head>

    <body class="font-sans antialiased scroll-smooth" x-data="{ sideBarOpen: false }">

        @livewire('partials.nav-bar')

        @livewire('partials.side-bar')
        
        <main class="py-5 px-6 md:py-0 md:ml-68">
            {{ $slot }}
        </main>

        @if(session('toast'))
            <script lang="text/javascript">
                window.addEventListener('DOMContentLoaded', function () {
                    window.toast(JSON.parse(@json(session('toast'))))
                });
            </script>
        @endif
        <x-ui.toast />

        @livewireScripts
    </body>
</html>