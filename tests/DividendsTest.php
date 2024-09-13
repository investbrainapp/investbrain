<?php

namespace Tests;

use App\Models\Dividend;
use Tests\TestCase;
use App\Models\User;
use App\Models\Split;
use App\Models\Holding;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DividendsTest extends TestCase
{
    use RefreshDatabase;

    /**
     */
    public function test_new_dividends_update_holding(): void
    {
        $this->actingAs($user = User::factory()->create());
        
        $portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->yearsAgo()->portfolio($portfolio->id)->symbol('ACME')->create();

        $holding = Holding::query()->portfolio($portfolio->id)->symbol('ACME')->first();
        
        $this->assertEquals(0, $holding->dividends_earned);

        Dividend::refreshDividendData('ACME');

        $holding->refresh();

        $this->assertEquals(4.95, $holding->dividends_earned);
    }
}
