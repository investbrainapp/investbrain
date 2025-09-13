<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-base-200">
    <head>
        @include('components.partials.head')
    </head>

    <body class="font-sans antialiased" x-data="{ sideBarOpen: false }">

        @livewire('partials.nav-bar')

        @livewire('partials.side-bar')
        
        <main class="py-5 px-6 md:py-0 md:ml-64">
            {{ $slot }}
        </main>

        @if(session('toast'))
            <script lang="text/javascript">
                window.addEventListener('DOMContentLoaded', function () {
                    window.toast(JSON.parse(@json(session('toast'))))
                });
            </script>
        @endif
        <x-ib-toast />

        @livewireScripts
    </body>
</html>