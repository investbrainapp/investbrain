<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HoldingResource extends JsonResource
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
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
            'quantity' => $this->quantity,
            'reinvest_dividends' => $this->reinvest_dividends,
            'average_cost_basis' => $this->average_cost_basis,
            'total_cost_basis' => $this->total_cost_basis,
            'realized_gain_dollars' => $this->realized_gain_dollars,
            'dividends_earned' => $this->dividends_earned,
            'splits_synced_at' => $this->splits_synced_at,
            'total_market_value' => $this->total_market_value,
            'market_gain_dollars' => $this->market_gain_dollars,
            'market_gain_percent' => $this->market_gain_percent,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
