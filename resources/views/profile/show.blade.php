<x-layouts.app>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')

                <x-ui.section-border hide-on-mobile />
            @endif
            
            <div class="mt-10 sm:mt-0">
                @livewire('localization-form')
            </div>

            <x-ui.section-border hide-on-mobile />
            
            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.update-password-form')
                </div>

                <x-ui.section-border hide-on-mobile />
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.two-factor-authentication-form')
                </div>

                <x-ui.section-border hide-on-mobile />
            @endif

            <div class="mt-10 sm:mt-0">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            <x-ui.section-border hide-on-mobile />

            <div class="mt-10 sm:mt-0">
                @livewire('profile.delete-user-form')
            </div>

        </div>
    </div>
</x-layouts.app>
