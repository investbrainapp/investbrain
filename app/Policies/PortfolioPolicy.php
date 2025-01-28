<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;

class PortfolioPolicy
{
    public function readOnly(User $user, Portfolio $portfolio)
    {
        $pivot = $portfolio->users()->where('user_id', $user->id)->first();

        return (bool) $pivot;
    }

    public function fullAccess(User $user, Portfolio $portfolio)
    {
        $pivot = $portfolio->users()->where('user_id', $user->id)->first();

        return $pivot && ($pivot->pivot->full_access || $pivot->pivot->owner);
    }

    public function owner(User $user, Portfolio $portfolio)
    {
        $pivot = $portfolio->users()->where('user_id', $user->id)->first();

        return $pivot && $pivot->pivot->owner;
    }
}
