<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AdanosMarketSentimentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketSentiment extends Model
{
    use HasFactory;

    protected $table = 'market_sentiment';

    protected $primaryKey = 'symbol';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'symbol',
        'average_buzz',
        'average_bullish_pct',
        'coverage',
        'source_alignment',
        'reddit_buzz',
        'reddit_bullish_pct',
        'reddit_mentions',
        'x_buzz',
        'x_bullish_pct',
        'x_mentions',
        'news_buzz',
        'news_bullish_pct',
        'news_mentions',
        'polymarket_buzz',
        'polymarket_bullish_pct',
        'polymarket_trade_count',
        'payload',
    ];

    protected $casts = [
        'average_buzz' => 'float',
        'average_bullish_pct' => 'float',
        'coverage' => 'integer',
        'reddit_buzz' => 'float',
        'reddit_bullish_pct' => 'integer',
        'reddit_mentions' => 'integer',
        'x_buzz' => 'float',
        'x_bullish_pct' => 'integer',
        'x_mentions' => 'integer',
        'news_buzz' => 'float',
        'news_bullish_pct' => 'integer',
        'news_mentions' => 'integer',
        'polymarket_buzz' => 'float',
        'polymarket_bullish_pct' => 'integer',
        'polymarket_trade_count' => 'integer',
        'payload' => 'array',
    ];

    public function holdings()
    {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function scopeSymbol($query, string $symbol)
    {
        return $query->where('symbol', strtoupper($symbol));
    }

    public static function enabled(): bool
    {
        return app(AdanosMarketSentimentService::class)->enabled();
    }

    public static function getMarketSentiment(string $symbol, bool $force = false): ?self
    {
        if (! self::enabled()) {
            return null;
        }

        $symbol = strtoupper(trim($symbol));

        if ($symbol === '') {
            return null;
        }

        $marketSentiment = self::firstOrNew([
            'symbol' => $symbol,
        ]);

        if (
            $force
            || ! $marketSentiment->exists
            || is_null($marketSentiment->updated_at)
            || $marketSentiment->updated_at->diffInMinutes(now()) >= (int) config('services.adanos.market_sentiment_refresh', 360)
        ) {
            $payload = app(AdanosMarketSentimentService::class)->fetch($symbol);

            if ($payload !== null) {
                $marketSentiment->fill($payload);
                $marketSentiment->save();
            }
        }

        return $marketSentiment->exists ? $marketSentiment : null;
    }
}
