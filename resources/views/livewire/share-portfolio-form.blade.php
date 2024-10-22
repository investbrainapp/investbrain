<?php

use App\Models\Portfolio;
use App\Models\User;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Illuminate\Support\Collection;
use Mary\Traits\Toast;
use App\Notifications\InvitedToPortfolioNotification;

new class extends Component {

    use Toast;

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
        if (!$this->portfolio) {
            $this->permissions = [
                auth()->user()->id => [
                    'owner' => true,
                    'full_access' => false
                ]
            ];

        } else {

            $this->permissions = collect($this->portfolio->users)->mapWithKeys(function ($user) {
                return [
                    $user->id => [
                        'owner' => $user->pivot->owner ?? 0,
                        'full_access' => $user->pivot->full_access ?? 0
                    ]
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

        if (!$confirmed) {
            $this->deletingAccessFor = $userId;
            $this->confirmingAccessDeletion = true;

            return;
        }
        
        unset($this->permissions[$userId]);

        $this->portfolio->users()->sync($this->permissions);

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

        $user = User::firstOrCreate([
            'email' => $this->emailAddress
        ], [
            'name' => Str::title(Str::before($this->emailAddress, '@'))
        ]);

        $this->permissions[$user->id] = [
            'full_access' => $this->fullAccess
        ];

        $sync = $this->portfolio->users()->sync($this->permissions);

        if (!empty($sync['attached'])) {

            foreach($sync['attached'] as $newUserId) {
                User::find($newUserId)->notify(new InvitedToPortfolioNotification($this->portfolio, auth()->user()));
            };
        }

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

    <div class="border-primary border rounded-sm px-2 py-5 mb-2">
        <x-list-item 
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
        </x-list-item>

        @foreach (collect($this->portfolio?->users)->where('pivot.owner', '!=', 1) as $user)
            <x-list-item 
                :item="$user" 
                avatar="profile_photo_url" 
                value="name" 
                no-separator
                class="!-my-2 rounded"
                x-data="{ loading: false, timeout: null }"
            >
                <x-slot:sub-value>
                    {{ $user->email }}
                </x-slot:sub-value>
                <x-slot:actions>
                    <x-select 
                        class="select select-ghost border-none focus:outline-none focus:ring-0"
                        :options="[['id' => 0, 'name' => __('Read only')], ['id' => 1, 'name' => __('Full access')]]"
                        wire:model.live.number="permissions.{{ $user->id }}.full_access"
                    />
                    @if($user->id != auth()->user()->id)
                    <x-button 
                        class="btn-sm btn-ghost btn-circle" 
                        wire:click="deleteUser('{{ $user->id }}')"
                        spinner="deleteUser"
                    >
                        <x-icon name="o-x-mark" class="w-4" />
                    </x-button>
                    @endif
   
                </x-slot:actions>
            </x-list-item>
        @endforeach

        <x-confirmation-modal wire:model.live="confirmingAccessDeletion">
            <x-slot:title>
                {{ __('Remove Access') }}
            </x-slot:title>
    
            <x-slot name="content">
                {{ __('By removing this person\'s access, they will no longer be able to view this portfolio. They will lose access immediately.') }}
            </x-slot>
    
            <x-slot name="footer">
                <x-button class="btn-outline" wire:click="$toggle('confirmingAccessDeletion')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>
    
                <x-button class="ms-3 btn-error text-white" wire:click="deleteUser('{{ $this->deletingAccessFor }}', true)" spinner="deleteUser" wire:loading.attr="disabled">
                    {{ __('Remove Access') }}
                </x-button>
            </x-slot>
        </x-confirmation-modal>

        <x-ib-alpine-modal 
            key="add-user-modal"
            title="{{ __('Share Portfolio') }}"
        >
            <div class="" x-data="{  }">
                <x-ib-form wire:submit="addUser" class="">
            
                    <x-input 
                        label="Email" 
                        icon="o-envelope" 
                        placeholder="{{ __('Type an email address to share portfolio') }}"
                        wire:model="emailAddress" 
                        required
                    />
                
                    <x-toggle 
                        class="mt-2"
                        label="{{ __('Grant full access') }}" 
                        wire:model="fullAccess" 
                        hint="{{ __('Allow this user to manage portfolio details and create or update transactions') }}"
                        right
                    />
                    
                    <x-slot:actions>
                    
                        <x-button 
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
            
        </x-ib-alpine-modal>

        <x-button class="btn-sm block mt-4" @click="$dispatch('toggle-add-user-modal')">
            {{ __('Add People') }}
        </x-button>
    </div>
</div>