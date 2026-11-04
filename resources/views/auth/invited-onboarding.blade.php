<x-layouts.guest>
    <x-ui.authentication-card>
        <x-slot:logo>
            <div class="w-24 mb-10">
                <x-ui.logo />
            </div>
        </x-slot:logo>

        <h1 class="text-2xl font-bold mb-4">{{ __('Hey again!') }} 👋</h1>
        <p class="mb-2">{{ __('Before you can get started with Investbrain, let\'s complete your profile:') }}</p>

        @livewire('invited-onboarding-form', [
            'portfolio' => $portfolio,
            'user' => $user,
            'expiresAt' => $expiresAt,
        ])

    </x-ui.authentication-card>
</x-layouts.guest>
