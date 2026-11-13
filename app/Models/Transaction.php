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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Pipeline;

class Transaction extends Model
{
    use HasFactory;
    use HasMarketData;
    use HasUuids;

    /**
     * The earliest date a transaction can have. Daily performance, price history and currency rates
     * are all built day by day from a portfolio's first transaction, so an ancient date would make them enormous.
     */
    public const EARLIEST_DATE = '1970-01-01';

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

    /**
     * Oldest first, with a day's purchases ahead of its sales so shares bought and sold the same day
     * are owned before they're sold
     */
    public function scopeInTradingOrder($query): Builder
    {
        return $query->orderByRaw(self::dateSql('transactions.date'))
            ->orderByRaw("CASE WHEN transactions.transaction_type = 'BUY' THEN 0 ELSE 1 END")
            ->orderBy('transactions.created_at');
    }

    /**
     * Quantity of a transaction as SQL, positive for a buy and negative for a sale. Summed over a
     * holding's transactions it gives the shares owned.
     */
    public static function signedQuantitySql(): string
    {
        return "CASE transactions.transaction_type WHEN 'BUY' THEN transactions.quantity WHEN 'SELL' THEN -transactions.quantity ELSE 0 END";
    }

    /**
     * The calendar date of a date column as SQL.
     *
     * SQLite has no date type, and the same table can hold 'Y-m-d' and 'Y-m-d H:i:s' text depending
     * on whether the row was written by Eloquent or as a plain string, so comparing columns as text
     * fails on the same day. PostgreSQL stores real dates, so the column is left bare to keep its index.
     */
    public static function dateSql(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "DATE({$column})" : $column;
    }

    /**
     * Adds the gain per share: the sale price for a sale, otherwise the current market value, less the cost basis
     */
    public function scopeWithGainDollars($query): Builder
    {
        return $query->selectRaw("
            (CASE WHEN transactions.transaction_type = 'SELL'
                THEN COALESCE(transactions.sale_price, 0)
                ELSE COALESCE((SELECT market_data.market_value FROM market_data WHERE market_data.symbol = transactions.symbol), 0)
            END) - COALESCE(transactions.cost_basis, 0) AS gain_dollars
        ");
    }

    /**
     * Shares owned across the transactions matching the query
     */
    public function scopeSharesOwned($query): float
    {
        return (float) $query->sum(DB::raw(self::signedQuantitySql()));
    }

    public function scopeMyTransactions($query): Builder
    {
        return $query->whereHas('portfolio', function ($query) {
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

        // if the symbol or portfolio changed, sync the holding the transaction moved out of too
        if (Arr::hasAny($this->changes, ['symbol', 'portfolio_id'])) {

            $temp = new Transaction;
            $temp->symbol = $this->original['symbol'];
            $temp->portfolio_id = $this->original['portfolio_id'];

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
