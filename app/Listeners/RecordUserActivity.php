<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Closure;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecordUserActivity
{
    /**
     * Decides whether an event should be ignored
     */
    protected static ?Closure $ignoreUsing = null;

    /**
     * @param  (Closure(Login|Authenticated): bool)|null  $callback
     */
    public static function ignoreUsing(?Closure $callback): void
    {
        static::$ignoreUsing = $callback;
    }

    /**
     * Record when a user last logged in and was last seen (at most once an hour).
     */
    public function handle(Login|Authenticated $event): void
    {
        if (! $event->user instanceof User || (static::$ignoreUsing && (static::$ignoreUsing)($event))) {
            return;
        }

        $userId = $event->user->getKey();

        try {
            // Written through the query builder so activity doesn't change the user's updated_at
            if ($event instanceof Login) {
                User::whereKey($userId)->toBase()->update([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                    'last_seen_at' => now(),
                ]);
            } elseif (Cache::add("user-last-seen:{$userId}", true, now()->addHour())) {
                User::whereKey($userId)->toBase()->update(['last_seen_at' => now()]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
