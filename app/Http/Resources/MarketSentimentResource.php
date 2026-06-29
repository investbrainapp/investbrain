<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketSentimentResource extends JsonResource
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
            'average_buzz' => $this->average_buzz,
            'average_bullish_pct' => $this->average_bullish_pct,
            'coverage' => $this->coverage,
            'source_alignment' => $this->source_alignment,
            'sources' => [
                'reddit' => [
                    'buzz' => $this->reddit_buzz,
                    'bullish_pct' => $this->reddit_bullish_pct,
                    'mentions' => $this->reddit_mentions,
                ],
                'x' => [
                    'buzz' => $this->x_buzz,
                    'bullish_pct' => $this->x_bullish_pct,
                    'mentions' => $this->x_mentions,
                ],
                'news' => [
                    'buzz' => $this->news_buzz,
                    'bullish_pct' => $this->news_bullish_pct,
                    'mentions' => $this->news_mentions,
                ],
                'polymarket' => [
                    'buzz' => $this->polymarket_buzz,
                    'bullish_pct' => $this->polymarket_bullish_pct,
                    'trade_count' => $this->polymarket_trade_count,
                ],
            ],
            'updated_at' => $this->updated_at,
        ];
    }
}
