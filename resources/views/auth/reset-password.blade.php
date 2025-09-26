<x-layouts.guest>
    <x-ui.authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-ui.logo />
            </div>
        </x-slot>

        <x-ui.errors class="mb-4" />

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="block">
                
                <x-ui.input id="email" label="{{ __('Email') }}" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            </div>

            <div class="mt-4">
                
                <x-ui.input id="password" label="{{ __('Password') }}" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                
                <x-ui.input id="password_confirmation" label="{{ __('Confirm Password') }}" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-ui.button class="btn-primary" type="submit">
                    {{ __('Reset Password') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.authentication-card>
</x-layouts.guest>
