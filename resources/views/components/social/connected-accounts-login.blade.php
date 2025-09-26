<div>
    @if(!empty(config('services.enabled_login_providers')))
    @foreach(explode(',', config('services.enabled_login_providers')) as $provider)
        <x-ui.button 
            link="{{ route('oauth.redirect', ['provider' => $provider]) }}" 
            class="btn-sm btn-block my-1 text-white" 
            style='background-color: {{ config("services.$provider.color") }}'
            no-wire-navigate
        >
            @include("components.social.$provider-icon")

            {{ __('Login with') }} {{ config("services.$provider.name") }} 
        </x-ui.button>
    @endforeach
    @endif
</div>