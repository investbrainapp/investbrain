<?php

use App\Traits\Toast;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;

    /**
     * Indicates if the application is confirming if a connected account should be removed.
     *
     * @var bool
     */
    public $confirmingDisconnect = false;

    /**
     * The ID of the connected account being removed.
     *
     * @var string|null
     */
    public $accountIdBeingDisconnected;

    /**
     * Get one row per connected account, plus one per enabled provider that has none.
     *
     * @return \Illuminate\Support\Collection<int, array{provider: string, name: string, color: ?string, account: ?\App\Models\ConnectedAccount}>
     */
    public function rows()
    {
        $accounts = Auth::user()->connectedAccounts()->get()->groupBy('provider');

        $providers = collect(explode(',', (string) config('services.enabled_login_providers')))
            ->filter()
            ->merge($accounts->keys())
            ->unique();

        return $providers->flatMap(function (string $provider) use ($accounts) {
            $row = [
                'provider' => $provider,
                'name' => config("services.$provider.name", $provider),
                'color' => config("services.$provider.color"),
            ];

            return $accounts->get($provider, collect([null]))
                ->map(fn ($account) => [...$row, 'account' => $account]);
        })->values();
    }

    /**
     * Confirm that the given connected account should be removed.
     *
     * @param  string  $accountId
     * @return void
     */
    public function confirmDisconnect($accountId)
    {
        $this->confirmingDisconnect = true;

        $this->accountIdBeingDisconnected = $accountId;
    }

    /**
     * Remove the connected account.
     *
     * @return void
     */
    public function disconnect()
    {
        Auth::user()->connectedAccounts()->whereKey($this->accountIdBeingDisconnected)->firstOrFail()->delete();

        $this->confirmingDisconnect = false;

        $this->accountIdBeingDisconnected = null;

        $this->success(__('Account disconnected.'));
    }
}; ?>

<div>
    @php($rows = $this->rows())

    @if ($rows->isNotEmpty())
        <x-forms.action-section>
            <x-slot name="title">
                {{ __('Social Logins') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Connect accounts from social providers to log in to Investbrain.') }}
            </x-slot>

            <x-slot name="content">
                <div class="space-y-6">
                    @foreach ($rows as $row)
                        <div class="flex items-center justify-between" wire:key="connected-account-{{ $row['account']?->id ?? $row['provider'] }}">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center size-8 rounded text-white" style="background-color: {{ $row['color'] }}">
                                    @includeIf("components.social.{$row['provider']}-icon")
                                </span>

                                <div>
                                    <div>{{ $row['name'] }}</div>

                                    @if ($row['account'])
                                        <div class="text-sm text-gray-400">
                                            @if ($row['account']->verified_at)
                                                {{ __('Connected') }} {{ $row['account']->created_at->diffForHumans() }}
                                            @else
                                                {{ __('Pending email verification') }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($row['account'])
                                <button class="cursor-pointer ms-6 text-sm text-red-500" wire:click="confirmDisconnect('{{ $row['account']->id }}')">
                                    {{ __('Disconnect') }}
                                </button>
                            @else
                                <x-ui.button
                                    link="{{ route('oauth.redirect', ['provider' => $row['provider']]) }}"
                                    class="btn-sm btn-outline"
                                    external
                                >
                                    {{ __('Connect') }}
                                </x-ui.button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-slot>
        </x-forms.action-section>

        <x-ui.section-border hide-on-mobile />
    @endif

    {{-- Disconnect Confirmation Modal --}}
    <x-ui.confirmation-modal key="confirm-disconnect-modal" wire:model.live="confirmingDisconnect">
        <x-slot name="title">
            {{ __('Disconnect Account') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to disconnect this account? You can connect it again at any time.') }}
        </x-slot>

        <x-slot name="footer">
            <x-ui.button class="btn-outline" wire:click="$toggle('confirmingDisconnect')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-ui.button>

            <x-ui.button class="ms-3 btn-error text-white" wire:click="disconnect" wire:loading.attr="disabled">
                {{ __('Disconnect') }}
            </x-ui.button>
        </x-slot>
    </x-ui.confirmation-modal>
</div>
