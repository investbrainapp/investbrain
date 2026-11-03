<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Http\ApiControllers\Controller as ApiController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RateLimitTest extends ApiTestCase
{
    use RefreshDatabase;

    public function test_requests_across_the_api_share_one_limit_per_user(): void
    {
        $this->actingAs(User::factory()->create());

        for ($i = 0; $i < ApiController::REQUESTS_PER_MINUTE; $i++) {
            $this->getJson(route('api.me'))->assertOk();
        }

        $this->getJson(route('api.portfolio.index'))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.portfolio.index'))
            ->assertOk();
    }
}
