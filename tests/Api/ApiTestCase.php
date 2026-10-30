<?php

declare(strict_types=1);

namespace Tests\Api;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Laravel\Passport\Passport;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->usePassportKeys();
    }

    /**
     * Authenticate through the Passport-backed "api" guard that protects the API routes.
     *
     * @param  string|null  $guard
     */
    public function actingAs(UserContract $user, $guard = null): static
    {
        Passport::actingAs($user, [], 'api');

        return $this;
    }
}
