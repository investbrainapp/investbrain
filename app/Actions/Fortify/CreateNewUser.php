<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Traits\WithTrimStrings;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use WithTrimStrings;

    public function trimExceptions()
    {
        return ['password'];
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => config('investbrain.self_hosted') ? '' : ['accepted', 'required'],
        ])->validate();

        $user = User::make([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        // ensure first user is flagged as an admin
        if (User::count() === 0) {
            $user->admin = true;
        }

        $user->save();

        return $user;
    }
}
