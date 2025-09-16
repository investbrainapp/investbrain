<x-layouts.guest>
    <x-ui.authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-ui.logo />
            </div>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        @session('status')
            <x-ui.alert icon="o-envelope" class="alert-success mb-4">
                {{ $value }}
            </x-ui.alert>
        @endsession

        <x-ui.errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="block">

                <x-ui.input id="email" label="{{ __('Email') }}" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-ui.button class="btn-primary" type="submit">
                    {{ __('Email Password Reset Link') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.authentication-card>
</x-layouts.guest>
