<x-common-layout>
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
        
            <x-toast />
        </div>
  
    </x-slot:body>
</x-common-layout>