<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Portfolio;
use Illuminate\Http\Request;

class InvitedOnboardingController extends Controller
{

    /**
     * Check if the invited user needs a password?
     *
     */
    public function __invoke(Request $request, Portfolio $portfolio, User $user)
    {

        if (!$request->hasValidSignature()) {
            abort(401, 'Invalid signature');
        }

        // user doesn't have password
        if (is_null($user->password)) {

            // route to create password form
            return view('auth.invited-onboarding', [
                'portfolio' => $portfolio,
                'user' => $user
            ]);
        }

        // redirect user to portfolio
        return redirect(route('portfolio.show', ['portfolio' => $portfolio->id]));
    }
}
