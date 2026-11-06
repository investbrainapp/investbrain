<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\VerifyConnectedAccountNotification;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
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

        // signed-in users are linking an account, not logging in
        if (Auth::check()) {

            return $this->linkToCurrentUser($provider, $providerUser);
        }

        // check if this account is already linked
        $connected_account = ConnectedAccount::firstOrNew([
            'provider' => $provider,
            'provider_id' => $providerUser->id,
        ]);

        $this->fillTokens($connected_account, $providerUser);

        // already linked and verified, let's go login!
        if (
            $connected_account->exists
            && ! is_null($connected_account->verified_at)
        ) {

            $connected_account->save();

            Auth::login($connected_account->user, true);

            return redirect(route('dashboard'));
        }

        // new user, let's create one
        if (! $user = User::where('email', Str::lower(trim((string) $providerUser->email)))->first()) {

            $user = User::make([
                'name' => $providerUser->name,
                'email' => $providerUser->email,
            ]);

            // email_verified_at isn't mass assignable, so User::create() used to drop it silently
            $user->forceFill(['email_verified_at' => now()])->save();

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

    /**
     * Link the provider account to the signed-in user.
     */
    protected function linkToCurrentUser(string $provider, mixed $providerUser): RedirectResponse
    {
        $user = Auth::user();
        $providerName = config("services.$provider.name");

        $connected_account = ConnectedAccount::firstOrNew([
            'provider' => $provider,
            'provider_id' => $providerUser->id,
        ]);

        if ($connected_account->exists && ! $user->ownsConnectedAccount($connected_account)) {

            return $this->redirectWithToast(
                route('connections.index'),
                __('That :provider account is already connected to another user.', ['provider' => $providerName]),
                'alert-error',
                'o-x-circle',
            );
        }

        $this->fillTokens($connected_account, $providerUser);
        $connected_account->user_id = $user->id;
        $connected_account->verified_at ??= now();
        $connected_account->save();

        return $this->redirectWithToast(
            route('connections.index'),
            __('Your :provider account has been connected.', ['provider' => $providerName]),
        );
    }

    /**
     * Copy the provider's current tokens onto the connected account, so a returning sign-in replaces stale ones.
     */
    protected function fillTokens(ConnectedAccount $connectedAccount, mixed $providerUser): ConnectedAccount
    {
        return $connectedAccount->fill([
            'token' => $providerUser->token,
            'secret' => $providerUser->tokenSecret,
            // providers such as Google only issue a refresh token on first consent, so keep the one we have
            'refresh_token' => $providerUser->refreshToken ?? $connectedAccount->refresh_token,
            // Socialite reports a lifetime in seconds, not a timestamp
            'expires_at' => isset($providerUser->expiresIn) ? now()->addSeconds((int) $providerUser->expiresIn) : null,
        ]);
    }

    /**
     * Redirect to the given URL and flash a toast notification.
     */
    protected function redirectWithToast(string $url, string $title, string $css = 'alert-success', string $icon = 'o-check-circle'): RedirectResponse
    {
        return redirect($url)->with('toast', json_encode([
            'toast' => [
                'title' => $title,
                'description' => null,
                'css' => $css,
                // the icon is passed as view data rather than spliced into the template, as the Toast trait does
                'icon' => Blade::render('<x-ui.icon class="w-7 h-7" :name="$icon" />', ['icon' => $icon]),
                'position' => 'toast-top toast-end',
                'timeout' => '5000',
            ],
        ]));
    }

    protected function validateProvider($provider): void
    {
        if (! in_array($provider, explode(',', config('services.enabled_login_providers')))) {

            throw new Exception('Please provide a valid social provider.');
        }
    }

    public function verify(Request $request, ConnectedAccount $connected_account)
    {
        // the link was issued to one user; refuse it if the account now belongs to another
        abort_unless($connected_account->user_id === $request->query('user'), 403);

        if (! $connected_account->verified_at) {

            // mark request as verified
            $connected_account->verified_at = now();
            $connected_account->save();

            // mark user as verified
            $connected_account->user->email_verified_at = now();
            $connected_account->user->save();

            Auth::login($connected_account->user, true);
        }

        return $this->redirectWithToast(
            route('dashboard'),
            __('Your :provider account has been connected.', ['provider' => config("services.{$connected_account->provider}.name")]),
        );
    }
}
