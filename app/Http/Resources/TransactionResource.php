<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'portfolio_id' => $this->portfolio_id,
            'transaction_type' => $this->transaction_type,
            'quantity' => $this->quantity,
            'currency' => $this->market_data->currency,
            'cost_basis' => $this->cost_basis,
            'sale_price' => $this->sale_price,
            'split' => $this->split,
            'reinvested_dividend' => $this->reinvested_dividend,
            'date' => $this->date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
