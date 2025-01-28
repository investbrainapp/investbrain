<?php

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\VerifyConnectedAccountNotification;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Laravel\Socialite\Facades\Socialite;

class ConnectedAccountController extends Controller
{
    /**
     * Redirect the user to the GitHub authentication page.
     */
    public function redirectToProvider(string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from GitHub.
     */
    public function handleProviderCallback(string $provider)
    {
        $this->validateProvider($provider);

        try {

            $providerUser = Socialite::driver($provider)->user();

        } catch (Exception $e) {

            return redirect(route('login'))
                ->with('errors', new MessageBag([__('Could not login using :provider. Try again later.', ['provider' => config("services.$provider.name")])]));
        }

        // check if this account is already linked
        $connected_account = ConnectedAccount::firstOrNew([
            'provider' => $provider,
            'provider_id' => $providerUser->id,
        ], [
            'token' => $providerUser->token,
            'secret' => $providerUser->tokenSecret,
            'refresh_token' => $providerUser->refreshToken,
            'expires_at' => $providerUser->expiresIn,
            'verified_at' => false,
        ]);

        // already linked and verified, let's go login!
        if (
            $connected_account->exists
            && ! is_null($connected_account->verified_at)
        ) {

            Auth::login($connected_account->user, true);

            return redirect(route('dashboard'));
        }

        // new user, let's create one
        if (! $user = User::where('email', $providerUser->email)->first()) {

            $user = User::create([
                'name' => $providerUser->name,
                'email' => $providerUser->email,
                'email_verified_at' => now(),
            ]);

            $connected_account->user_id = $user->id;
            $connected_account->verified_at = now();
            $connected_account->save();

            Auth::login($user, true);

            return redirect(route('dashboard'));
        }

        // email exists already, send verification link
        $connected_account->user_id = $user->id;
        $connected_account->save();

        $user->notify(new VerifyConnectedAccountNotification($connected_account->id));

        return redirect(route('login'))
            ->with('status', __(
                'Account already exists. Check your email to connect your :provider account.',
                ['provider' => config("services.$provider.name")]
            ));
    }

    protected function validateProvider($provider): void
    {
        if (! in_array($provider, explode(',', config('services.enabled_login_providers')))) {

            throw new Exception('Please provide a valid social provider.');
        }
    }

    public function verify(ConnectedAccount $connected_account)
    {
        if (! $connected_account->verified_at) {

            // mark request as verified
            $connected_account->verified_at = now();
            $connected_account->save();

            // mark user as verified
            $connected_account->user->email_verified_at = now();
            $connected_account->user->save();

            Auth::login($connected_account->user, true);
        }

        return redirect(route('dashboard'))->with('toast', json_encode([
            'toast' => [
                'title' => __('Your :provider account has been connected.', ['provider' => config("services.{$connected_account->provider}.name")]),
                'description' => null,
                'css' => 'alert-success',
                'icon' => Blade::render("<x-mary-icon class='w-7 h-7' name='o-check-circle' />"),
                'position' => 'toast-top toast-end',
                'timeout' => '5000',
            ],
        ]));
    }
}
