<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\ConnectedAccount;
use Illuminate\Support\MessageBag;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Models\ConnectedAccountVerification;
use App\Notifications\VerifyConnectedAccountNotification;

class ConnectedAccountController extends Controller
{

    /**
     * Redirect the user to the GitHub authentication page.
     *
     */
    public function redirectToProvider(string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from GitHub.
     *
     */
    public function handleProviderCallback(string $provider)
    {
        $this->validateProvider($provider);

        try {

            $providerUser = Socialite::driver($provider)->user();

        } catch (Exception $e) {

            return redirect(route('login'))
                ->with('error',new MessageBag([__('Could not login using that provider. Try again later.')]));
        }

        // check if this account is already linked
        $connected_account = ConnectedAccount::firstOrNew([ 
            'provider' => $provider,
            'provider_id' => $providerUser->id
        ], [
            'token' => $providerUser->token,
            'secret' => $providerUser->tokenSecret,
            'refresh_token' => $providerUser->refreshToken,
            'expires_at' => $providerUser->expiresIn
        ]);

        // already linked, let's go login
        if ($connected_account->exists) {
            Auth::login($connected_account->user, true);

            return redirect(route('dashboard'));
        }

        // new user, let's create one
        if (!$user = User::where('email', $providerUser->email)->first()) {

            $user = User::create([
                'name' => $providerUser->name,
                'email' => $providerUser->email,
                'email_verified_at' => now()
            ]);
    
            $connected_account->user_id = $user->id;
            $connected_account->save();
    
            Auth::login($user, true);
    
            return redirect(route('dashboard'));
        }

        // email exists already, send verification link
        $verification = ConnectedAccountVerification::updateOrCreate([
            'email' => $providerUser->email,
            'provider' => $provider,
            'verified_at' => null
        ], [
            'provider_id' => $providerUser->id,
            'connected_account' => $connected_account
        ]);

        $user->notify(new VerifyConnectedAccountNotification($verification->id));

        return redirect(route('login'))
                ->with('status', __('Account already exists. Check your email to connect your accounts.'));
    }

    protected function validateProvider($provider): void
    {
        if (!in_array($provider, explode(',', config('services.enabled_login_providers')))) {
            
            throw new Exception('Please provide a valid social provider.');
        }
    }

    public function verify(string $verification_id)
    {

        $verification = ConnectedAccountVerification::findOrFail($verification_id);

        if (!$verification->verified_at) {

            // mark request as verified
            $verification->verified_at = now();
            $verification->save();

            // mark user as verified
            $user = User::where('email', $verification->email)->firstOrFail();
            $user->email_verified_at = now();
            $user->save();

            // add connected account
            $user->connectedAccounts()->create([
                ...$verification->connected_account, 
                ...[
                    'provider' => $verification->provider,
                    'provider_id' => $verification->provider_id,
                ]
            ]);

            Auth::login($user, true);
        }

        return redirect(route('dashboard'));
    }
}
