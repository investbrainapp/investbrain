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

        try {

            return Socialite::driver($provider)->redirect();

        } catch (Exception $e) {

            report($e);

            return $this->redirectToLoginWithError(__('Could not login using :provider. Try again later.', ['provider' => config("services.$provider.name")]));
        }
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

            report($e);

            return $this->redirectToLoginWithError(__('Could not login using :provider. Try again later.', ['provider' => config("services.$provider.name")]));
        }

        // signed-in users are linking an account, not logging in
        if (Auth::check()) {

            return $this->linkToCurrentUser($provider, $providerUser);
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
        ]);

        // already linked and verified, let's go login!
        if (
            $connected_account->exists
            && ! is_null($connected_account->verified_at)
        ) {

            Auth::login($connected_account->user, true);

            return redirect(route('dashboard'));
        }

        // an address the provider hasn't verified can't be used to find or create an account
        if (blank($providerUser->email) || ! ($providerUser->email_verified ?? true)) {

            return $this->redirectToLoginWithError(__('Your :provider account doesn\'t have a verified email address.', ['provider' => config("services.$provider.name")]));
        }

        // new user, let's create one
        if (! $user = User::where('email', Str::lower(trim((string) $providerUser->email)))->first()) {

            if (! config('services.sso_auto_provision')) {

                return $this->redirectToLoginWithError(__('There is no account for your :provider login. Create an account or ask an administrator for one first.', ['provider' => config("services.$provider.name")]));
            }

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
        ], [
            'token' => $providerUser->token,
            'secret' => $providerUser->tokenSecret,
            'refresh_token' => $providerUser->refreshToken,
            'expires_at' => $providerUser->expiresIn,
        ]);

        if ($connected_account->exists && ! $user->ownsConnectedAccount($connected_account)) {

            return $this->redirectWithToast(
                route('connections.index'),
                __('That :provider account is already connected to another user.', ['provider' => $providerName]),
                'alert-error',
                'o-x-circle',
            );
        }

        $connected_account->user_id = $user->id;
        $connected_account->verified_at ??= now();
        $connected_account->save();

        return $this->redirectWithToast(
            route('connections.index'),
            __('Your :provider account has been connected.', ['provider' => $providerName]),
        );
    }

    /**
     * Send the user back to the login page with an error.
     */
    protected function redirectToLoginWithError(string $message): RedirectResponse
    {
        return redirect(route('login'))->with('errors', new MessageBag([$message]));
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
