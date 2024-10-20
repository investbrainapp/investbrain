<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-glyph-only-logo />
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
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
                </label>
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

                <x-section-border />

                <div class="">

                    @foreach(explode(',', config('services.enabled_login_providers')) as $provider)
                        <x-button 
                            link="{{ route('oauth.redirect', ['provider' => $provider]) }}" 
                            class="btn-sm btn-block my-1" 
                            style='background-color: {{ config("services.$provider.color") }}'
                            no-wire-navigate
                        >
                            @include("components.$provider-icon")
                    
                            {{ __('Login with') }} {{ config("services.$provider.name") }} 
                        </x-button>
                    @endforeach
                    
                    <x-button 
                        link="{{ route('register') }}" 
                        class="btn-sm btn-block btn-outline btn-secondary my-1" 
                    >
                        {{ __('Sign up with email') }}
                    </x-button>

                </div>
            @endif
        </form>
    </x-authentication-card>
</x-guest-layout>
