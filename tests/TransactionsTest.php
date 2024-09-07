<?php

namespace Tests;

use App\Models\User;
use App\Models\Portfolio;
use App\Models\Transaction;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     */
    public function test_can_create_a_transaction(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $transactions = Transaction::factory()->create();
    }
}




        // static::saving(function ($transaction) {

        //     if ($transaction->transaction_type == 'SELL') {

        //         $transaction->ensureCostBasisIsAddedToSale();
        //     }
        // });

        // static::saved(function ($transaction) {

        //     $transaction->syncToHolding();

        //     $transaction->refreshMarketData();

        //     cache()->tags(['metrics', $transaction->portfolio_id])->flush();
        // });

        // public function update()
        // {
            
        //     $this->transaction->update($this->validate());
        //     // $this->transaction->owner_id = auth()->user()->id;
        //     $this->transaction->save();

        //     $this->success(__('Transaction updated'));

        //     $this->dispatch('toggle-manage-transaction');
        //     $this->dispatch('transaction-updated');
        // }

        // public function save()
        // {
        //     $validated = $this->validate();

        //     if (!isset($this->portfolio)) {
        //         $this->portfolio = Portfolio::find($this->portfolio_id);
        //     }

        //     $transaction = $this->portfolio->transactions()->create($validated);
        //     $transaction->save();

        //     $this->dispatch('transaction-saved');

        //     $this->success(__('Transaction created'), redirectTo: route('holding.show', ['portfolio' => $this->portfolio->id, 'symbol' => $this->symbol]));
        // }
