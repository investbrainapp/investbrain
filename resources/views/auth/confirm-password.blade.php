<x-layouts.guest>
    <x-ui.authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-ui.logo />
            </div>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </div>

        <x-ui.errors class="mb-4" />

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div>
                <x-ui.input id="password" label="{{ __('Password') }}" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" autofocus />
            </div>

            <div class="flex justify-end mt-4">
                <x-ui.button type="submit" class="btn-primary ms-4">
                    {{ __('Confirm') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.authentication-card>
</x-layouts.guest>
