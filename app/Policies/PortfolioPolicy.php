<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Portfolio;

class PortfolioPolicy
{

    /**
     * 
     */
    public function readOnly(User $user, Portfolio $portfolio)
    {
        $pivot = $portfolio->users()->where('user_id', $user->id)->first();

        return !!$pivot;
    }

    /**
     * 
     */
    public function fullAccess(User $user, Portfolio $portfolio)
    {
        $pivot = $portfolio->users()->where('user_id', $user->id)->first();

        return $pivot && ($pivot->pivot->full_access || $pivot->pivot->owner);
    }

    /**
     * 
     */
    public function owner(User $user, Portfolio $portfolio)
    {
        $pivot = $portfolio->users()->where('user_id', $user->id)->first();

        return $pivot && $pivot->pivot->owner;
    }
}
