<x-layouts.guest>
    <x-ui.authentication-card>
        <x-slot name="logo">
            <div class="w-24 mb-10">
                <x-ui.logo />
            </div>
        </x-slot>

        <div class="mb-4 text-lg font-semibold">
            {{ __('Authorize :client', ['client' => $client->name]) }}
        </div>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __(':client would like permission to access your Investbrain account (:email).', ['client' => $client->name, 'email' => $user->email]) }}
        </div>

        @if (count($scopes) > 0)
            <div class="mb-4">
                <div class="text-sm font-semibold">
                    {{ __('This application will be able to:') }}
                </div>

                <ul class="mt-2 list-disc ps-5 text-sm text-gray-600 dark:text-gray-400">
                    @foreach ($scopes as $scope)
                        <li>{{ $scope->description }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex items-center justify-end gap-3 mt-6">
            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')

                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                <x-ui.button type="submit" class="btn-outline">
                    {{ __('Cancel') }}
                </x-ui.button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf

                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                <x-ui.button type="submit" class="btn-primary">
                    {{ __('Authorize') }}
                </x-ui.button>
            </form>
        </div>
    </x-ui.authentication-card>
</x-layouts.guest>
