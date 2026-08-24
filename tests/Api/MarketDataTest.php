<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Models\MarketData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_get_market_data_for_symbol(): void
    {
        MarketData::getMarketData('AAPL');

        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'AAPL']))
            ->assertOk()
            ->assertJsonStructure([
                'symbol',
                'name',
                'market_value',
                'fifty_two_week_low',
                'fifty_two_week_high',
                'last_dividend_date',
                'last_dividend_amount',
                'dividend_yield',
                'market_cap',
                'trailing_pe',
                'forward_pe',
                'book_value',
                'created_at',
                'updated_at',
            ]);
    }

    public function test_market_data_returns_correct_symbol(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'ACME']))
            ->assertSuccessful()
            ->assertJsonFragment([
                'symbol' => 'ACME',
            ]);
    }

    public function test_market_data_response_has_expected_fields(): void
    {
        MarketData::getMarketData('MSFT');

        $this->actingAs($this->user)
            ->getJson(route('api.market-data.show', ['symbol' => 'MSFT']))
            ->assertOk()
            ->assertJsonPath('symbol', 'MSFT')
            ->assertJsonPath('market_value', 230.19);
    }

    public function test_cannot_access_market_data_when_unauthenticated(): void
    {
        $this->getJson(route('api.market-data.show', ['symbol' => 'AAPL']))->assertUnauthorized();
    }
}
