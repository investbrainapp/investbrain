<x-layouts.guest>
    <x-authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-ib-logo />
            </div>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        @session('status')
            <x-alert icon="o-envelope" class="alert-success mb-4">
                {{ $value }}
            </x-alert>
        @endsession

        <x-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="block">

                <x-input id="email" label="{{ __('Email') }}" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-button class="btn-primary" type="submit">
                    {{ __('Email Password Reset Link') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-layouts.guest>
