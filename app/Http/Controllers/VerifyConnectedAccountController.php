<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use Illuminate\Support\Facades\Auth;
use App\Models\ConnectedAccountVerification;

class VerifyConnectedAccountController extends Controller
{

    public function __invoke(string $verification_id)
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

            Auth::login($user);
        }

        return redirect(route('dashboard'));
    }
}
