<?php

use App\Models\Portfolio;
use App\Models\User;
use Livewire\Attributes\Rule;
use Livewire\Volt\Component;
use Illuminate\Support\Collection;
use Mary\Traits\Toast;

new class extends Component {

    use Toast;

    // props
    public ?Portfolio $portfolio = null;

    #[Rule('required|string|email')]
    public string $emailAddress;

    #[Rule('sometimes|boolean')]
    public int $fullAccess = 0;

    public array $permissions;

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

    public function deleteUser(string $userId)
    {
        $this->authorize('fullAccess', $this->portfolio);

        unset($this->permissions[$userId]);

        $this->portfolio->users()->sync($this->permissions);

        $this->portfolio->refresh();

        $this->success(__('Removed user\'s access to portfolio'));
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

        $this->portfolio->users()->sync($this->permissions);

        $this->success(__('Shared portfolio with user'));
        $this->portfolio->refresh();

        $this->dispatch('toggle-add-user-modal');

        $this->emailAddress = '';
        $this->fullAccess = false;
    }

}; ?>

<div class="">
    @if ($this->portfolio)
    
    <label class="pt-0 label label-text font-semibold">
        <span>{{ __('People with access') }}</span>
    </label>

    <div class="border-primary border rounded-sm px-2 py-5 mb-2">
        @php
            $owner = collect($this->portfolio?->users)->where('pivot.owner', 1)->first() ?? auth()->user();
        @endphp
        <x-list-item 
            :item="$owner" 
            avatar="profile_photo_url" 
            no-separator
            no-hover 
            class="!-my-2 rounded"
        >
            <x-slot:value>
            
                {{ $owner->name }}

                @if (auth()->user()->id == $owner->id) 
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
                    <x-button 
                        class="btn-sm btn-ghost btn-circle" 
                        wire:click="deleteUser('{{ $user->id }}')"
                        spinner="deleteUser"
                    >
                        <x-icon name="o-x-mark" class="w-4" />
                    </x-button>
   
                </x-slot:actions>
            </x-list-item>
        @endforeach

        <x-ib-modal 
            key="add-user-modal"
            title="{{ __('Share portfolio') }}"
        >
            <div class="" x-data="{  }">
                <x-ib-form wire:submit="addUser" class="">
            
                    <x-input 
                        label="Email" 
                        icon="o-envelope" 
                        placeholder="{{ __('Type an email address to share portfolio') }}"
                        wire:model="emailAddress" 
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
                            title="{{ __('Share portfolio') }}"
                            type="submit" 
                            icon="o-paper-airplane" 
                            class="btn-primary" 
                            spinner="addUser"
                        />
                    </x-slot:actions>
                </x-ib-form>
        
            </div>
            
        </x-ib-modal>

        <x-button class="btn-sm block mt-4" @click="$dispatch('toggle-add-user-modal')">
            {{ __('Add people') }}
        </x-button>
        
    </div>
    @endif
</div>