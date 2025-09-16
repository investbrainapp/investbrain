<x-layouts.guest>
    <x-ui.authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-ui.logo />
            </div>
        </x-slot>

        <x-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div>
                
                <x-ui.input id="name" label="{{ __('Name') }}" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div class="mt-4">
                
                <x-ui.input id="email" label="{{ __('Email') }}" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            </div>

            <div class="mt-4">
                
                <x-ui.input id="password" label="{{ __('Password') }}" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                
                <x-ui.input id="password_confirmation" label="{{ __('Confirm Password') }}" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (! config('investbrain.self_hosted'))
                <div class="mt-4">
                    <label>
                        <div class="flex items-center">
                            <x-checkbox name="terms" id="terms" required />

                            <div class="ms-2 text-sm">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="https://investbra.in/terms" class="underline">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="https://investbra.in/privacy" class="underline">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </label>
                </div>
            @endif

            <div class="flex items-center justify-end mt-4">
                <a class=" text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-ui.button type="submit" class="btn-primary ms-4">
                    {{ __('Register') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.authentication-card>
</x-layouts.guest>
