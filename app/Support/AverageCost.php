<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Transaction;

/**
 * Average cost of the shares owned in a holding, fed its transactions oldest first.
 *
 * A purchase adds its shares and their cost. A sale takes shares out at the average cost of the
 * shares owned at that moment, which leaves the average unchanged. Once every share is sold the
 * cost starts over, so shares bought later aren't averaged with ones that are long gone.
 *
 * Costs are tracked both in the market data currency (cost_basis) and the base currency
 * (cost_basis_base), each at the rate on the day of the purchase.
 */
class AverageCost
{
    public float $quantity = 0;

    public float $totalCost = 0;

    public float $totalCostBase = 0;

    /**
     * Walks the transactions, oldest first
     *
     * @param  iterable<Transaction>  $transactions
     */
    public static function of(iterable $transactions): self
    {
        $averageCost = new self;

        foreach ($transactions as $transaction) {
            $averageCost->apply($transaction);
        }

        return $averageCost;
    }

    /**
     * Adds a purchase or takes out a sale
     *
     * @return array{cost_basis: float, cost_basis_base: float}|null the cost of the shares sold, for a sale
     */
    public function apply(Transaction $transaction): ?array
    {
        if ($transaction->transaction_type === 'SELL') {
            return $this->sell((float) $transaction->quantity);
        }

        if ($transaction->transaction_type === 'BUY') {
            $this->buy((float) $transaction->quantity, (float) $transaction->cost_basis, (float) $transaction->cost_basis_base);
        }

        return null;
    }

    public function buy(float $quantity, float $costBasis, float $costBasisBase): void
    {
        $this->quantity += $quantity;
        $this->totalCost += $quantity * $costBasis;
        $this->totalCostBase += $quantity * $costBasisBase;
    }

    /**
     * @return array{cost_basis: float, cost_basis_base: float} the average cost per share of the shares sold
     */
    public function sell(float $quantity): array
    {
        $sold = [
            'cost_basis' => $this->averageCostBasis(),
            'cost_basis_base' => $this->averageCostBasisBase(),
        ];

        $this->quantity -= $quantity;

        if (round($this->quantity, 4) <= 0) {
            $this->quantity = 0;
            $this->totalCost = 0;
            $this->totalCostBase = 0;
        } else {
            $this->totalCost -= $quantity * $sold['cost_basis'];
            $this->totalCostBase -= $quantity * $sold['cost_basis_base'];
        }

        return $sold;
    }

    public function averageCostBasis(): float
    {
        return $this->quantity > 0 ? $this->totalCost / $this->quantity : 0;
    }

    public function averageCostBasisBase(): float
    {
        return $this->quantity > 0 ? $this->totalCostBase / $this->quantity : 0;
    }
}
