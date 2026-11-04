<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Lower-case existing emails, which new and updated accounts now store that way.
     *
     * An account is left as it is when another account already has the lower-case address,
     * because merging two accounts can't be done safely here.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereRaw('email <> lower(email)')
            ->orderBy('id')
            ->each(function (object $user) {
                $email = Str::lower($user->email);

                if (DB::table('users')->where('email', $email)->exists()) {
                    return;
                }

                DB::table('users')->where('id', $user->id)->update(['email' => $email]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // the original casing isn't kept, so there's nothing to restore
    }
};
