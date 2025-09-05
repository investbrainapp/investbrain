<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('components.partials.head')
    </head>

    <body class="font-sans antialiased min-h-screen" x-data="{}">
    
        <main class=""">
            <x-ib-theme-selector hidden="true" />
            
            {{ $slot }}
        </main>
        
        @livewireScripts
    </body>
</html>