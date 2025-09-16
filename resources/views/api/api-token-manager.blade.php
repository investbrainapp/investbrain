<?php

namespace Laravel\Jetstream\Http\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Jetstream;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * The create API token form state.
     *
     * @var array
     */
    public $createApiTokenForm = [
        'name' => '',
        'permissions' => [],
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
     * Indicates if the user is currently managing an API token's permissions.
     *
     * @var bool
     */
    public $managingApiTokenPermissions = false;

    /**
     * The token that is currently having its permissions managed.
     *
     * @var \Laravel\Sanctum\PersonalAccessToken|null
     */
    public $managingPermissionsFor;

    /**
     * The update API token form state.
     *
     * @var array
     */
    public $updateApiTokenForm = [
        'permissions' => [],
    ];

    /**
     * Indicates if the application is confirming if an API token should be deleted.
     *
     * @var bool
     */
    public $confirmingApiTokenDeletion = false;

    /**
     * The ID of the API token being deleted.
     *
     * @var int
     */
    public $apiTokenIdBeingDeleted;

    /**
     * Mount the component.
     *
     * @return void
     */
    public function mount()
    {
        $this->createApiTokenForm['permissions'] = Jetstream::$defaultPermissions;
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
            $this->createApiTokenForm['name'],
            Jetstream::validPermissions($this->createApiTokenForm['permissions'])
        ));

        $this->createApiTokenForm['name'] = '';
        $this->createApiTokenForm['permissions'] = Jetstream::$defaultPermissions;

        $this->dispatch('created');
    }

    /**
     * Display the token value to the user.
     *
     * @param  \Laravel\Sanctum\NewAccessToken  $token
     * @return void
     */
    protected function displayTokenValue($token)
    {
        $this->displayingToken = true;

        $this->plainTextToken = explode('|', $token->plainTextToken, 2)[1];

        $this->dispatch('showing-token-modal');
    }

    /**
     * Allow the given token's permissions to be managed.
     *
     * @param  int  $tokenId
     * @return void
     */
    public function manageApiTokenPermissions($tokenId)
    {
        $this->managingApiTokenPermissions = true;

        $this->managingPermissionsFor = $this->user->tokens()->where(
            'id', $tokenId
        )->firstOrFail();

        $this->updateApiTokenForm['permissions'] = $this->managingPermissionsFor->abilities;
    }

    /**
     * Update the API token's permissions.
     *
     * @return void
     */
    public function updateApiToken()
    {
        $this->managingPermissionsFor->forceFill([
            'abilities' => Jetstream::validPermissions($this->updateApiTokenForm['permissions']),
        ])->save();

        $this->managingApiTokenPermissions = false;
    }

    /**
     * Confirm that the given API token should be deleted.
     *
     * @param  int  $tokenId
     * @return void
     */
    public function confirmApiTokenDeletion($tokenId)
    {
        $this->confirmingApiTokenDeletion = true;

        $this->apiTokenIdBeingDeleted = $tokenId;
    }

    /**
     * Delete the API token.
     *
     * @return void
     */
    public function deleteApiToken()
    {
        $this->user->tokens()->where('id', $this->apiTokenIdBeingDeleted)->first()->delete();

        $this->user->load('tokens');

        $this->confirmingApiTokenDeletion = false;

        $this->managingPermissionsFor = null;
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
} ?>

<div>
    <!-- Generate API Token -->
    <x-forms.form-section submit="createApiToken">
        <x-slot name="title">
            {{ __('Create API Token') }}
        </x-slot>

        <x-slot name="description">
            {{ __('API tokens allow third-party services to authenticate with Investbrain on your behalf.') }}
        </x-slot>

        <x-slot name="form">
            <!-- Token Name -->
            <div class="col-span-6 sm:col-span-4">
                <x-ui.input id="name" label="{{ __('Token Name') }}" type="text" class="mt-1 block w-full" wire:model="createApiTokenForm.name" autofocus />
            </div>

            <!-- Token Permissions -->
            @if (Laravel\Jetstream\Jetstream::hasPermissions())
                <div class="col-span-6">
                    <label class="pt-0 label label-text font-semibold">
                        <span>
                            {{ __('Permissions') }}
                        </span>
                    </span>

                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach (Laravel\Jetstream\Jetstream::$permissions as $label => $permission)
                            <label class="flex items-center">
                                <x-ui.checkbox wire:model="createApiTokenForm.permissions" :value="$permission"/>
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
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

    @if ($this->user->tokens->isNotEmpty())
        <x-ui.section-border hide-on-mobile />

        <!-- Manage API Tokens -->
        <div class="mt-10 sm:mt-0">
            <x-forms.action-section>
                <x-slot name="title">
                    {{ __('Manage API Tokens') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('You may delete any of your existing tokens if they are no longer needed.') }}
                </x-slot>

                <!-- API Token List -->
                <x-slot name="content">
                    <div class="space-y-6">
                        @foreach ($this->user->tokens->sortBy('name') as $token)
                            <div class="flex items-center justify-between">
                                <div class="break-all dark:text-white">
                                    {{ $token->name }}
                                </div>

                                <div class="flex items-center ms-2">
                                    @if ($token->last_used_at)
                                        <div class="text-sm text-gray-400">
                                            {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                        </div>
                                    @endif

                                    @if (Laravel\Jetstream\Jetstream::hasPermissions())
                                        <button class="cursor-pointer ms-6 text-sm text-gray-400 underline" wire:click="manageApiTokenPermissions({{ $token->id }})">
                                            {{ __('Permissions') }}
                                        </button>
                                    @endif

                                    <button class="cursor-pointer ms-6 text-sm text-red-500" wire:click="confirmApiTokenDeletion({{ $token->id }})">
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

    <!-- Token Value Modal -->
    <x-ui.dialog-modal wire:model.live="displayingToken">
        <x-slot name="title">
            {{ __('API Token') }}
        </x-slot>

        <x-slot name="content">
            <div>
                {{ __('Please copy your new API token. For your security, it won\'t be shown again.') }}
            </div>

            <x-ui.input x-ref="plaintextToken" type="text" readonly :value="$plainTextToken"
                class="mt-4 px-4 py-2 rounded font-mono text-sm w-full break-all"
                autofocus autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                @showing-token-modal.window="setTimeout(() => $refs.plaintextToken.select(), 250)"
            />
        </x-slot>

        <x-slot name="footer">
            <x-ui.button class="btn-outline" wire:click="$set('displayingToken', false)" wire:loading.attr="disabled">
                {{ __('Close') }}
            </x-ui.button>
        </x-slot>
    </x-ui.dialog-modal>

    <!-- API Token Permissions Modal -->
    <x-ui.dialog-modal wire:model.live="managingApiTokenPermissions">
        <x-slot name="title">
            {{ __('API Token Permissions') }}
        </x-slot>

        <x-slot name="content">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach (Laravel\Jetstream\Jetstream::$permissions as $label => $permission)
                    <label class="flex items-center">
                        <x-ui.checkbox wire:model="updateApiTokenForm.permissions" :value="$permission"/>
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-ui.button class="btn-outline" wire:click="$set('managingApiTokenPermissions', false)" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-ui.button>

            <x-ui.button type="submit" class="ms-3" wire:click="updateApiToken" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-ui.button>
        </x-slot>
    </x-ui.dialog-modal>

    <!-- Delete Token Confirmation Modal -->
    <x-ui.confirmation-modal wire:model.live="confirmingApiTokenDeletion">
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
