<x-guest-layout>
    <x-authentication-card>
        <x-slot:logo>
            <div class="w-24 mb-10">
                <x-glyph-only-logo />
            </div>
        </x-slot:logo>

        <h1 class="text-2xl font-bold mb-4">{{ __('Hey again!') }} 👋</h1>
        <p class="mb-2">{{ __('Before you can get started with Investbrain, you\'ll want to create a password:') }}</p>

        @livewire('invited-onboarding-form', [
            'portfolio' => $portfolio,
            'user' => $user,
        ])

        <x-section-border />

        <p class="mb-4">{{ __('Or login with SSO:') }}</p>

        <x-connected-accounts-login />

    </x-authentication-card>
</x-guest-layout>
