<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\ConvertToMarketDataCurrency;
use App\Actions\CopyToBaseCurrency;
use App\Actions\EnsureCostBasisAddedToSale;
use App\Actions\EnsureDailyChangeIsSynced;
use App\Casts\BaseCurrency;
use App\Traits\HasMarketData;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Pipeline;

class Transaction extends Model
{
    use HasFactory;
    use HasMarketData;
    use HasUuids;

    protected $fillable = [
        'symbol',
        'date',
        'portfolio_id',
        'transaction_type',
        'currency',
        'quantity',
        'cost_basis',
        'sale_price',
        'split',
        'reinvested_dividend',
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'split' => 'boolean',
        'reinvested_dividend' => 'boolean',
        'quantity' => 'float',
        'cost_basis' => 'float',
        'sale_price' => 'float',
        'cost_basis_base' => BaseCurrency::class,
        'sale_price_base' => BaseCurrency::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($transaction) {

            $transaction = Pipeline::send($transaction)
                ->through([
                    ConvertToMarketDataCurrency::class,
                    EnsureCostBasisAddedToSale::class,
                    CopyToBaseCurrency::class,
                ])
                ->then(fn (Transaction $transaction) => $transaction);
        });

        static::saved(function ($transaction) {

            $transaction->syncToHolding();

            $transaction = Pipeline::send($transaction)
                ->through([
                    EnsureDailyChangeIsSynced::class,
                ])
                ->then(fn (Transaction $transaction) => $transaction);

            cache()->forget('portfolio-metrics-'.$transaction->portfolio_id);
        });

        static::deleted(function ($transaction) {

            $transaction->syncToHolding();

            cache()->forget('portfolio-metrics-'.$transaction->portfolio_id);
        });
    }

    /**
     * Ensure transaction symbol is always upper case
     */
    protected function symbol(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper($value)
        );
    }

    /**
     * Related portfolio
     *
     * @return void
     */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function scopeWithMarketData($query): Builder
    {
        return $query->withAggregate('market_data', 'name')
            ->withAggregate('market_data', 'market_value')
            ->withAggregate('market_data', 'currency')
            ->withAggregate('market_data', 'fifty_two_week_low')
            ->withAggregate('market_data', 'fifty_two_week_high')
            ->withAggregate('market_data', 'updated_at')
            ->join('market_data', 'transactions.symbol', 'market_data.symbol');
    }

    public function scopePortfolio($query, $portfolio): Builder
    {
        return $query->where('portfolio_id', $portfolio);
    }

    public function scopeSymbol($query, $symbol): Builder
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeBuy($query): Builder
    {
        return $query->where('transaction_type', 'BUY');
    }

    public function scopeSell($query): Builder
    {
        return $query->where('transaction_type', 'SELL');
    }

    public function scopeBeforeDate($query, $date): Builder
    {
        return $query->whereDate('date', '<=', $date);
    }

    public function scopeMyTransactions(): Builder
    {
        return $this->whereHas('portfolio', function ($query) {
            $query->whereHas('users', function ($query) {
                $query->where('id', auth()->id());
            });
        });
    }

    /**
     * Syncs the holding related to this transaction
     */
    public function syncToHolding(): void
    {

        // if symbol name changed, sync previous symbol too
        if (Arr::has($this->changes, 'symbol')) {

            $temp = new Transaction;
            $temp->symbol = $this->original['symbol'];
            $temp->portfolio_id = $this->portfolio_id;

            $temp->syncToHolding();
        }

        // get the holding for a symbol and portfolio (or create one)
        Holding::firstOrNew([
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
        ], [
            'portfolio_id' => $this->portfolio_id,
            'symbol' => $this->symbol,
            'quantity' => $this->quantity,
            'average_cost_basis' => $this->cost_basis_base,
            'total_cost_basis' => $this->quantity * $this->cost_basis_base,
            'splits_synced_at' => now(),
        ])->syncTransactionsAndDividends();
    }
}
