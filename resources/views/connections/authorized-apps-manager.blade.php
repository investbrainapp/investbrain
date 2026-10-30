<?php

use App\Traits\Toast;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;

    /**
     * Indicates if the application is confirming if an app's access should be revoked.
     *
     * @var bool
     */
    public $confirmingRevoke = false;

    /**
     * The ID of the OAuth client whose access is being revoked.
     *
     * @var string|null
     */
    public $clientIdBeingRevoked;

    /**
     * Get the apps the user has authorized, one entry per OAuth client.
     *
     * A token counts as active while it is unrevoked and either unexpired or still refreshable, since clients
     * exchange their refresh token for a new access token. Personal access tokens are managed on the API Tokens screen.
     *
     * @return \Illuminate\Support\Collection<int, array{client: \Laravel\Passport\Client, scopes: array<int, string>, authorized_at: \Illuminate\Support\Carbon}>
     */
    public function authorizedApps()
    {
        return Auth::user()->tokens()
            ->with(['client', 'refreshToken'])
            ->where('revoked', false)
            ->get()
            ->reject(fn ($token) => ! $token->client || $token->client->hasGrantType('personal_access'))
            ->filter(fn ($token) => is_null($token->expires_at)
                || $token->expires_at->isFuture()
                || ($token->refreshToken && ! $token->refreshToken->revoked && $token->refreshToken->expires_at?->isFuture())
            )
            ->groupBy('client_id')
            ->map(fn ($tokens) => [
                'client' => $tokens->first()->client,
                'scopes' => collect(Passport::scopesFor($tokens->pluck('scopes')->flatten()->unique()->all()))
                    ->pluck('description')
                    ->all(),
                'authorized_at' => $tokens->min('created_at'),
            ])
            ->sortBy(fn ($app) => $app['client']->name)
            ->values();
    }

    /**
     * Confirm that the given app's access should be revoked.
     *
     * @param  string  $clientId
     * @return void
     */
    public function confirmRevoke($clientId)
    {
        $this->confirmingRevoke = true;

        $this->clientIdBeingRevoked = $clientId;
    }

    /**
     * Revoke every token the user has issued to the OAuth client.
     *
     * @return void
     */
    public function revoke()
    {
        $tokens = Auth::user()->tokens()
            ->where('client_id', $this->clientIdBeingRevoked)
            ->get();

        abort_if($tokens->isEmpty(), 404);

        $tokens->each(function ($token) {
            $token->revoke();
            $token->refreshToken?->revoke();
        });

        $this->confirmingRevoke = false;

        $this->clientIdBeingRevoked = null;

        $this->success(__('Access revoked.'));
    }
}; ?>

<div>
    <x-forms.action-section>
        <x-slot name="title">
            {{ __('Authorized Apps') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Apps and AI assistants you have given access to your Investbrain account.') }}
        </x-slot>

        <x-slot name="content">
            @php($apps = $this->authorizedApps())

            @if ($apps->isEmpty())
                <div class="text-sm text-gray-400">
                    {{ __('No apps have access to your account.') }}
                </div>
            @else
                <div class="space-y-6">
                    @foreach ($apps as $app)
                        <div class="flex items-start justify-between" wire:key="authorized-app-{{ $app['client']->id }}">
                            <div class="break-all">
                                <div>{{ $app['client']->name }}</div>

                                @foreach ($app['scopes'] as $description)
                                    <div class="text-sm text-gray-400">{{ $description }}</div>
                                @endforeach

                                <div class="text-sm text-gray-400">
                                    {{ __('Authorized') }} {{ $app['authorized_at']->diffForHumans() }}
                                </div>
                            </div>

                            <button class="cursor-pointer ms-6 text-sm text-red-500" wire:click="confirmRevoke('{{ $app['client']->id }}')">
                                {{ __('Revoke') }}
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-slot>
    </x-forms.action-section>

    {{-- Revoke Confirmation Modal --}}
    <x-ui.confirmation-modal key="confirm-revoke-modal" wire:model.live="confirmingRevoke">
        <x-slot name="title">
            {{ __('Revoke Access') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to revoke this app\'s access? It will need your approval again before it can reconnect.') }}
        </x-slot>

        <x-slot name="footer">
            <x-ui.button class="btn-outline" wire:click="$toggle('confirmingRevoke')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-ui.button>

            <x-ui.button class="ms-3 btn-error text-white" wire:click="revoke" wire:loading.attr="disabled">
                {{ __('Revoke') }}
            </x-ui.button>
        </x-slot>
    </x-ui.confirmation-modal>
</div>
