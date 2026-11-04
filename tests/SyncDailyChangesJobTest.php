<?php

declare(strict_types=1);

namespace Tests;

use App\Jobs\SyncDailyChangesJob;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Queue;

class SyncDailyChangesJobTest extends TestCase
{
    use RefreshDatabase;

    public Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->portfolio = Portfolio::factory()->create();
        Transaction::factory()->buy()->lastMonth()->portfolio($this->portfolio->id)->symbol('AAPL')->create();
        Transaction::factory()->buy()->lastMonth()->portfolio($this->portfolio->id)->symbol('ACME')->create();
    }

    public function test_back_dated_transaction_queues_a_sync_instead_of_running_one(): void
    {
        Queue::fake();
        // the write-path trigger is switched off in the testing environment
        config(['app.env' => 'local']);

        Transaction::factory()->buy()->lastYear()->portfolio($this->portfolio->id)->symbol('AAPL')->create();

        Queue::assertPushed(SyncDailyChangesJob::class, fn (SyncDailyChangesJob $job) => $job->portfolio->is($this->portfolio));
        $this->assertDatabaseCount('daily_change', 0);
    }

    public function test_a_newer_sync_replaces_an_older_one(): void
    {
        Queue::fake();

        SyncDailyChangesJob::dispatchFor($this->portfolio);
        SyncDailyChangesJob::dispatchFor($this->portfolio);

        [$older, $newer] = Queue::pushed(SyncDailyChangesJob::class)->all();

        $older->handle();
        $this->assertDatabaseCount('daily_change', 0);

        $newer->handle();
        $this->assertDatabaseHas('daily_change', ['portfolio_id' => $this->portfolio->id]);
    }

    public function test_a_sync_replaced_while_running_stops_without_writing(): void
    {
        $checks = 0;

        // replaced after the first holding: the check before the second one says stop
        $this->portfolio->syncDailyChanges(function () use (&$checks) {
            return ++$checks > 1;
        });

        $this->assertSame(2, $checks);
        $this->assertDatabaseCount('daily_change', 0);
    }

    public function test_syncs_for_the_same_portfolio_run_one_at_a_time(): void
    {
        $job = new SyncDailyChangesJob($this->portfolio, 'token');
        $otherJob = new SyncDailyChangesJob(Portfolio::factory()->create(), 'token');

        $middleware = $job->middleware()[0];

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame($this->portfolio->id, $middleware->key);
        $this->assertNotSame($middleware->key, $otherJob->middleware()[0]->key);
    }
}
