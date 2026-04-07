<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\MarketSentiment;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasMarketSentiment
{
    public function market_sentiment(): HasOne
    {
        return $this->hasOne(MarketSentiment::class, 'symbol', 'symbol');
    }

    public function loadMarketSentiment(bool $force = false): void
    {
        if (! MarketSentiment::enabled()) {
            return;
        }

        $symbol = (string) $this->getAttribute('symbol');

        if ($symbol === '') {
            return;
        }

        $this->setRelation('market_sentiment', MarketSentiment::getMarketSentiment($symbol, $force));
    }
}
