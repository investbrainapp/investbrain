<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'symbol' => $this->symbol,
            'name' => $this->name,
            'market_value' => $this->market_value,
            'fifty_two_week_low' => $this->fifty_two_week_low,
            'fifty_two_week_high' => $this->fifty_two_week_high,
            'last_dividend_date' => $this->last_dividend_date,
            'last_dividend_amount' => $this->last_dividend_amount,
            'dividend_yield' => $this->dividend_yield,
            'market_cap' => $this->market_cap,
            'trailing_pe' => $this->trailing_pe,
            'forward_pe' => $this->forward_pe,
            'book_value' => $this->book_value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
