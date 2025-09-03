<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('components.partials.head')
    </head>

    <body class="font-sans antialiased min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    
        <main class=""">
            {{ $slot }}
        </main>
        
        @livewireScripts
        @fluxScripts
    </body>
</html>