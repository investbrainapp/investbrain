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

    public function test_only_one_sync_waits_per_portfolio(): void
    {
        Queue::fake();

        SyncDailyChangesJob::dispatch($this->portfolio);
        SyncDailyChangesJob::dispatch($this->portfolio);
        SyncDailyChangesJob::dispatch(Portfolio::factory()->create());

        // the second dispatch for the same portfolio is dropped; the waiting sync will see its data
        Queue::assertPushed(SyncDailyChangesJob::class, 2);
        Queue::assertPushed(SyncDailyChangesJob::class, fn (SyncDailyChangesJob $job) => $job->portfolio->is($this->portfolio));
    }

    public function test_another_sync_can_wait_once_the_waiting_one_starts_running(): void
    {
        // runs straight away on the sync queue, which starts processing it and frees its place
        SyncDailyChangesJob::dispatch($this->portfolio);
        $this->assertDatabaseHas('daily_change', ['portfolio_id' => $this->portfolio->id]);

        Queue::fake();

        SyncDailyChangesJob::dispatch($this->portfolio);

        Queue::assertPushed(SyncDailyChangesJob::class, 1);
    }

    public function test_syncs_for_the_same_portfolio_run_one_at_a_time(): void
    {
        $job = new SyncDailyChangesJob($this->portfolio);
        $otherJob = new SyncDailyChangesJob(Portfolio::factory()->create());

        $middleware = $job->middleware()[0];

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame($this->portfolio->id, $middleware->key);
        $this->assertNotSame($middleware->key, $otherJob->middleware()[0]->key);
    }
}
