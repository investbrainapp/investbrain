<div>
    @if(!empty(config('services.enabled_login_providers')))
    @foreach(explode(',', config('services.enabled_login_providers')) as $provider)
        <x-ib-button 
            link="{{ route('oauth.redirect', ['provider' => $provider]) }}" 
            class="btn-sm btn-block my-1" 
            style='background-color: {{ config("services.$provider.color") }}'
            no-wire-navigate
        >
            @include("components.$provider-icon")

            {{ __('Login with') }} {{ config("services.$provider.name") }} 
        </x-ib-button>
    @endforeach
    @endif
</div>