<?php

use App\Models\Portfolio;
use App\Traits\Toast;
use App\Traits\WithTrimStrings;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    use Toast;
    use WithTrimStrings;

    // props
    public ?Portfolio $portfolio = null;

    #[Rule('required|string|email')]
    public string $emailAddress;

    #[Rule('sometimes|boolean')]
    public int $fullAccess = 0;

    public array $permissions;

    public bool $confirmingAccessDeletion = false;

    public ?string $deletingAccessFor = null;

    // methods
    public function mount()
    {
        if (! $this->portfolio) {
            $this->permissions = [
                auth()->user()->id => [
                    'owner' => true,
                    'full_access' => false,
                ],
            ];

        } else {

            $this->permissions = collect($this->portfolio->users)->mapWithKeys(function ($user) {
                return [
                    $user->id => [
                        'owner' => $user->pivot->owner ?? 0,
                        'full_access' => $user->pivot->full_access ?? 0,
                    ],
                ];
            })->toArray();
        }
    }

    public function updatedPermissions()
    {
        $this->authorize('fullAccess', $this->portfolio);

        $this->portfolio->users()->sync($this->permissions);

        $this->portfolio->refresh();

        $this->success(__('Updated user\'s access permission to portfolio'));
    }

    public function deleteUser(string $userId, bool $confirmed = false)
    {
        $this->authorize('fullAccess', $this->portfolio);

        if (! $confirmed) {
            $this->deletingAccessFor = $userId;
            $this->confirmingAccessDeletion = true;

            return;
        }

        unset($this->permissions[$userId]);

        $this->portfolio->unShare($userId);

        $this->portfolio->refresh();

        $this->success(__('Removed user\'s access to portfolio'));

        // reset
        $this->confirmingAccessDeletion = false;
        $this->deletingAccessFor = null;
    }

    public function addUser()
    {
        $this->authorize('fullAccess', $this->portfolio);

        $this->validate();

        $this->portfolio->share($this->emailAddress, $this->fullAccess);

        $this->success(__('Shared portfolio with user'));
        $this->portfolio->refresh();

        $this->dispatch('toggle-add-user-modal');

        $this->emailAddress = '';
        $this->fullAccess = false;
    }
}; ?>

<div class="">
    <label class="pt-0 label label-text font-semibold">
        <span>{{ __('People with access') }}</span>
    </label>

    <div class="border-primary border rounded-sm px-2 py-5 mb-2 max-h-[20rem] overflow-y-scroll">
        @if ($portfolio?->owner)
        <x-ib-list-item 
            :item="$portfolio->owner" 
            avatar="profile_photo_url" 
            no-separator
            no-hover 
            class="!-my-2 rounded"
        >
            <x-slot:value>
            
                {{ $portfolio->owner->name }}

                @if (auth()->user()->id == $portfolio->owner->id) 
                    ({{ __('you') }})
                @endif
            </x-slot:value>
            <x-slot:sub-value>
                {{ __('Owner') }}
            </x-slot:sub-value>
        </x-ib-list-item>
        @endif

        @foreach (collect($portfolio?->users)->where('pivot.owner', '!=', 1) as $user)
            <x-ib-list-item 
                :item="$user" 
                avatar="profile_photo_url" 
                no-separator
                class="!-my-2 rounded"
                x-data="{ loading: false, timeout: null }"
            >

                <x-slot:value>
                    {{ $user->name }}

                    @if (auth()->user()->id == $user->id) 
                        ({{ __('you') }})
                    @endif
                </x-slot:value>
                <x-slot:sub-value>
                    {{ $user->email }}

                </x-slot:sub-value>
                <x-slot:actions>
                    @if (auth()->user()->id != $user->id) 
                    <x-ib-select 
                        class="select select-ghost border-none focus:outline-none focus:ring-0"
                        :options="[['id' => 0, 'name' => __('Read only')], ['id' => 1, 'name' => __('Full access')]]"
                        wire:model.live.number="permissions.{{ $user->id }}.full_access"
                    />
                    
                    <x-ib-button 
                        class="btn-sm btn-ghost btn-circle" 
                        wire:click="deleteUser('{{ $user->id }}')"
                        spinner="deleteUser('{{ $user->id }}')"
                        title="{{ __('Remove Access') }}"
                    >
                        <x-ib-icon name="o-x-mark" class="w-4" />
                    </x-ib-button>      
                    @endif
                </x-slot:actions>
            </x-ib-list-item>
        @endforeach

        <x-confirmation-modal wire:model.live="confirmingAccessDeletion">
            <x-slot:title>
                {{ __('Remove Access') }}
            </x-slot:title>
    
            <x-slot name="content">
                {{ __('By removing this person\'s access, they will no longer be able to view this portfolio. They will lose access immediately.') }}
            </x-slot>
    
            <x-slot name="footer">
                <x-ib-button class="btn-outline" wire:click="$toggle('confirmingAccessDeletion')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>
    
                <x-ib-button class="ms-3 btn-error text-white" wire:click="deleteUser('{{ $this->deletingAccessFor }}', true)" spinner="deleteUser" wire:loading.attr="disabled">
                    {{ __('Remove Access') }}
                </x-ib-button>
            </x-slot>
        </x-confirmation-modal>

        <x-ib-modal 
            key="add-user-modal"
            title="{{ __('Share Portfolio') }}"
        >
            <div class="" x-data="{  }">
                <x-ib-form wire:submit="addUser" class="">
            
                    <x-ib-input 
                        label="Email" 
                        icon="o-envelope" 
                        placeholder="{{ __('Type an email address to share portfolio') }}"
                        wire:model="emailAddress" 
                        type="email"
                        required
                    />
                
                    <x-ib-toggle 
                        class="mt-2"
                        label="{{ __('Grant full access') }}" 
                        wire:model="fullAccess" 
                        hint="{{ __('Allow this user to manage portfolio details and create or update transactions') }}"
                        right
                    />
                    
                    <x-slot:actions>
                    
                        <x-ib-button 
                            label="{{ __('Share') }}" 
                            title="{{ __('Share Portfolio') }}"
                            type="submit" 
                            icon="o-paper-airplane" 
                            class="btn-primary" 
                            spinner="addUser"
                        />
                    </x-slot:actions>
                </x-ib-form>
        
            </div>
            
        </x-ib-modal>

        <x-ib-button class="btn-sm block mt-4" @click="$dispatch('toggle-add-user-modal')">
            {{ __('Add People') }}
        </x-ib-button>
    </div>
</div>