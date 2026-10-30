<?php

use App\Traits\Toast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;

    /**
     * The create API token form state.
     *
     * @var array
     */
    public $createApiTokenForm = [
        'name' => '',
    ];

    /**
     * Indicates if the plain text token is being displayed to the user.
     *
     * @var bool
     */
    public $displayingToken = false;

    /**
     * The plain text token value.
     *
     * @var string|null
     */
    public $plainTextToken;

    /**
     * Indicates if the application is confirming if an API token should be deleted.
     *
     * @var bool
     */
    public $confirmingApiTokenDeletion = false;

    /**
     * The ID of the API token being deleted.
     *
     * @var string|null
     */
    public $apiTokenIdBeingDeleted;

    /**
     * Mount the component.
     *
     * @return void
     */
    public function mount()
    {
        //
    }

    /**
     * Create a new API token.
     *
     * @return void
     */
    public function createApiToken()
    {
        $this->resetErrorBag();

        Validator::make([
            'name' => $this->createApiTokenForm['name'],
        ], [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('createApiToken');

        $this->displayTokenValue($this->user->createToken(
            $this->createApiTokenForm['name']
        ));

        $this->createApiTokenForm['name'] = '';

        $this->dispatch('created');
    }

    /**
     * Display the token value to the user.
     *
     * @param  \Laravel\Passport\PersonalAccessTokenResult  $token
     * @return void
     */
    protected function displayTokenValue($token)
    {
        $this->displayingToken = true;

        $this->plainTextToken = $token->accessToken;
    }

    /**
     * Get the user's active (unrevoked and unexpired) personal access tokens.
     *
     * Tokens issued to OAuth clients (e.g. MCP clients) are excluded.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passport\Token>
     */
    public function activeTokens()
    {
        return $this->user->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->filter(fn ($token) => $token->client?->hasGrantType('personal_access'))
            ->values();
    }

    /**
     * Confirm that the given API token should be deleted.
     *
     * @param  string  $tokenId
     * @return void
     */
    public function confirmApiTokenDeletion($tokenId)
    {
        $this->confirmingApiTokenDeletion = true;

        $this->apiTokenIdBeingDeleted = $tokenId;
    }

    /**
     * Revoke the API token.
     *
     * @return void
     */
    public function deleteApiToken()
    {
        $this->user->tokens()->whereKey($this->apiTokenIdBeingDeleted)->firstOrFail()->revoke();

        $this->confirmingApiTokenDeletion = false;

        $this->apiTokenIdBeingDeleted = null;
    }

    /**
     * Get the current user of the application.
     *
     * @return mixed
     */
    public function getUserProperty()
    {
        return Auth::user();
    }
}; ?>

<div x-data>
    {{-- Generate API Token --}}
    <x-forms.form-section submit="createApiToken">
        <x-slot name="title">
            {{ __('Create API Token') }}
        </x-slot>

        <x-slot name="description">
            {{ __('API tokens allow third-party services to authenticate with Investbrain on your behalf.') }}
        </x-slot>

        <x-slot name="form">
            {{-- Token Name --}}
            <div class="col-span-6 sm:col-span-4">
                <x-ui.input id="name" label="{{ __('Token Name') }}" type="text" class="mt-1 block w-full" wire:model="createApiTokenForm.name" autofocus />
            </div>

            {{-- Token Value Modal --}}
            <x-ui.modal
                persistent
                key="token-display-modal"
                wire:model.live="displayingToken"
                title="{{ __('API Token') }}"
            >
                <div class="mt-2 text-sm text-secondary-content">

                    <div class="mb-4">
                        {{ __('Please copy your new API token. For your security, it won\'t be shown again.') }}
                    </div>

                    <x-ui.input 
                        x-ref="plaintextToken" 
                        type="text" 
                        readonly
                        :value="$plainTextToken"
                        class="font-mono break-all focus:outline-none focus:ring-0"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                    >
                        <x-slot:suffix>
                            <x-ui.button
                                title="{{ __('Copy to clipboard') }}"
                                class="btn-circle btn-sm btn-ghost me-2"
                                icon="o-clipboard"
                                @click="
                                    navigator.clipboard.writeText($wire.plainTextToken); 
                                    $wire.$set('displayingToken', false);
                                    $wire.success('{{ __('Successfully copied!') }}')
                                "
                            />
                        </x-slot:suffix>
                    </x-ui.input>
                </div>
        
                <div class="flex flex-row items-center justify-end mt-8 text-end">
                    <x-ui.button class="btn-outline" wire:click="$set('displayingToken', false)" wire:loading.attr="disabled">
                        {{ __('Close') }}
                    </x-ui.button>
                </div>
      
            </x-ui.modal>

        </x-slot>

        <x-slot name="actions">
            <x-forms.action-message class="me-3" on="created">
                {{ __('Created.') }}
            </x-forms.action-message>

            <x-ui.button type="submit">
                {{ __('Create') }}
            </x-ui.button>
        </x-slot>
    </x-forms.form-section>

    @php($tokens = $this->activeTokens())

    @if ($tokens->isNotEmpty())
        <x-ui.section-border hide-on-mobile />

        {{-- Manage API Tokens --}}
        <div class="mt-10 sm:mt-0">
            <x-forms.action-section>
                <x-slot name="title">
                    {{ __('Manage API Tokens') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('You may delete any of your existing tokens if they are no longer needed.') }}
                </x-slot>

                {{-- API Token List --}}
                <x-slot name="content">
                    <div class="space-y-6">
                        @foreach ($tokens->sortBy('name') as $token)
                            <div class="flex items-center justify-between" wire:key="token-{{ $token->id }}">
                                <div class="break-all">
                                    {{ $token->name ?? $token->client?->name }}
                                </div>

                                <div class="flex items-center ms-2">
                                    <div class="text-sm text-gray-400">
                                        {{ __('Created') }} {{ $token->created_at->diffForHumans() }}
                                    </div>

                                    <button class="cursor-pointer ms-6 text-sm text-red-500" wire:click="confirmApiTokenDeletion('{{ $token->id }}')">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-slot>
            </x-forms.action-section>
        </div>
    @endif

    {{-- Delete Token Confirmation Modal --}}
    <x-ui.confirmation-modal key="confirm-deletion-modal" wire:model.live="confirmingApiTokenDeletion">
        <x-slot name="title">
            {{ __('Delete API Token') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Are you sure you would like to delete this API token?') }}
        </x-slot>

        <x-slot name="footer">
            <x-ui.button class="btn-outline" wire:click="$toggle('confirmingApiTokenDeletion')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-ui.button>

            <x-ui.button class="ms-3 btn-error text-white" wire:click="deleteApiToken" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </x-ui.button>
        </x-slot>
    </x-ui.confirmation-modal>
</div>
