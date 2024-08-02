<x-common-layout>
    <x-slot:body class="font-sans text-gray-900 dark:text-gray-100 antialiased">

        {{ $slot }}

        <x-theme-toggle class="hidden" darkTheme="business" lightTheme="corporate"/>

    </x-slot:body>
</x-common-layout>
