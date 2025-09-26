<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('components.partials.head')
    </head>

    <body class="font-sans antialiased scroll-smooth min-h-screen my-12" x-data="{}">
    
        <main class="">
            <x-ui.theme-selector hidden="true" />
            
            {{ $slot }}
        </main>
        
        @livewireScripts
    </body>
</html>