<x-layouts.guest>
    <x-authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-ib-logo />
            </div>
        </x-slot>

        <x-errors class="mb-4" />

        @session('status')
            <x-alert icon="o-envelope" class="alert-success mb-4">
                {{ $value }}
            </x-alert>
        @endsession

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                
                <x-input id="email" label="{{ __('Email') }}" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="mt-4">
                
                <x-input id="password" label="{{ __('Password') }}" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <x-checkbox id="remember_me" name="remember" class="text-sm" label="{{ __('Remember me') }}" />
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-button type="submit" class="btn-primary ms-4" >
                    {{ __('Log in') }}
                </x-button>

            </div>

            @if (\Laravel\Fortify\Features::enabled('registration'))

                <x-ib-section-border />

                <x-connected-accounts-login />
                
                <x-button 
                    link="{{ route('register') }}" 
                    class="btn-sm btn-block btn-outline btn-secondary my-1" 
                >
                    {{ __('Sign up with email') }}
                </x-button>

            @endif
        </form>
    </x-authentication-card>
</x-layouts.guest>
