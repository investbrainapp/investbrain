<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\MarketData\MarketDataInterface;
use App\Traits\HasMarketData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Split extends Model
{
    use HasFactory;
    use HasMarketData;
    use HasUuids;

    /**
     * Precision that split amounts are reliably stored with (i.e. 0.3333 for a 1:3 reverse split)
     */
    protected const float SPLIT_AMOUNT_PRECISION = 0.00005;

    /**
     * Largest denominator considered when resolving a split amount into a simple ratio
     */
    protected const int MAX_RATIO_DENOMINATOR = 100;

    protected $fillable = [
        'symbol',
        'date',
        'split_amount',
        'dividends_adjusted_at',
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'last_date' => 'datetime',
        'dividends_adjusted_at' => 'datetime',
    ];

    public function holdings(): HasMany
    {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'symbol', 'symbol');
    }

    /**
     * Grab new split data
     *
     * @return void
     */
    public static function refreshSplitData(string $symbol)
    {
        // dates for split data
        $splits_meta = self::where(['symbol' => $symbol])
            ->selectRaw('COUNT(symbol) as total_splits')
            ->selectRaw('MAX(date) as last_date')
            ->get()
            ->first();

        // assume need to populate all split data because it didnt exist before
        $start_date = new \DateTime('@0');
        $end_date = now();

        // nope, need to populate newer split data
        if ($splits_meta->total_splits) {

            $start_date = $splits_meta->last_date->addHours(48);
            $end_date = now();
        }

        // get some data
        if ($split_data = collect() && $start_date && $end_date) {
            $split_data = app(MarketDataInterface::class)->splits($symbol, $start_date, $end_date);
        }

        $existing_split_dates = self::where(['symbol' => $symbol])
            ->get(['date'])
            ->map(fn (Split $split) => $split->date->toDateString());

        // ignore upcoming splits until they take effect (they will be fetched again)
        $new_split_data = $split_data->reject(
            fn ($split) => Carbon::parse($split['date'])->isAfter(now()->endOfDay())
                || $existing_split_dates->contains(Carbon::parse($split['date'])->toDateString())
        );

        if ($new_split_data->isNotEmpty()) {

            // insert records
            (new self)->insertOrIgnore($new_split_data->map(function ($split) {

                return [...$split, ...['id' => Str::uuid()->toString()]];
            })->values()->toArray());

            // dividends recorded before these splits are in pre-split terms
            $new_split_dates = $new_split_data->map(fn ($split) => Carbon::parse($split['date'])->toDateString());

            self::where(['symbol' => $symbol])
                ->whereNull('dividends_adjusted_at')
                ->get()
                ->filter(fn (Split $split) => $new_split_dates->contains($split->date->toDateString()))
                ->each(fn (Split $split) => $split->applyToDividends());
        }

        // sync to transactions
        self::syncToTransactions($symbol);

        if ($new_split_data->isNotEmpty()) {

            Dividend::syncHoldings($symbol);
        }
    }

    /**
     * Adjusts the existing transactions of each holding for any splits that occurred
     * after the holding last synced its splits
     *
     * Transactions dated before a split are restated in post-split terms (quantity is
     * multiplied by the split ratio while prices are divided by it), so the holding's
     * quantity reflects the split without changing its total cost basis
     */
    public static function syncToTransactions(string $symbol): void
    {
        $splits = self::where(['symbol' => $symbol])
            ->whereDate('date', '<=', now())
            ->orderBy('date', 'ASC')
            ->get();

        if ($splits->isEmpty()) {

            return;
        }

        Holding::where(['symbol' => $symbol])
            ->get()
            ->each(function (Holding $holding) use ($splits) {

                $pending_splits = $splits->filter(
                    fn (Split $split) => is_null($holding->splits_synced_at)
                        || $split->date->toDateString() > $holding->splits_synced_at->toDateString()
                );

                if ($pending_splits->isEmpty()) {

                    return;
                }

                DB::transaction(function () use ($holding, $pending_splits) {

                    $pending_splits->each(
                        fn (Split $split) => $split->applyToTransactions($holding->portfolio_id)
                    );

                    $holding->splits_synced_at = now();

                    $holding->syncTransactionsAndDividends();
                });

                cache()->forget('portfolio-metrics-'.$holding->portfolio_id);
            });
    }

    /**
     * Restates a portfolio's transactions dated before this split in post-split terms
     */
    public function applyToTransactions(string $portfolio_id): void
    {
        $ratio = $this->shareRatio();

        if ($ratio <= 0 || $ratio == 1) {

            return;
        }

        self::adjustTransactions(
            Transaction::where([
                'portfolio_id' => $portfolio_id,
                'symbol' => $this->symbol,
            ])->whereDate('date', '<', $this->date->toDateString()),
            $ratio
        );
    }

    /**
     * Restates per share dividend amounts recorded before this split in post-split terms
     *
     * Dividends fetched after the split are assumed to already be split adjusted by the
     * market data provider, so only dividends fetched before the split are adjusted
     */
    public function applyToDividends(): void
    {
        $ratio = $this->shareRatio();

        if ($ratio > 0 && $ratio != 1) {

            $price_multiplier = self::toSqlNumber(1 / $ratio);

            Dividend::where(['symbol' => $this->symbol])
                ->whereDate('date', '<', $this->date->toDateString())
                ->where('created_at', '<', $this->date->copy()->startOfDay())
                ->update([
                    'dividend_amount' => DB::raw("dividend_amount * {$price_multiplier}"),
                    'dividend_amount_base' => DB::raw("dividend_amount_base * {$price_multiplier}"),
                ]);
        }

        $this->dividends_adjusted_at = now();
        $this->save();
    }

    /**
     * Converts transactions created by the legacy split handling (which created a new "split"
     * transaction for the additional shares) into split adjusted transaction history
     *
     * @return array{repaired: int, skipped: int, dividends_adjusted: int}
     */
    public static function repairLegacySplitTransactions(): array
    {
        $results = ['repaired' => 0, 'skipped' => 0, 'dividends_adjusted' => 0];

        $affected_symbols = collect();

        Transaction::where(['split' => true])
            ->orderBy('date', 'ASC')
            ->get()
            ->groupBy(fn (Transaction $transaction) => $transaction->portfolio_id.'|'.$transaction->symbol)
            ->each(function (Collection $split_transactions) use (&$results, $affected_symbols) {

                $first = $split_transactions->first();

                // resolve every ratio before modifying anything
                $ratios = $split_transactions->mapWithKeys(
                    fn (Transaction $split_transaction) => [$split_transaction->id => self::legacySplitRatio($split_transaction)]
                );

                DB::transaction(function () use ($split_transactions, $ratios, $first, &$results) {

                    $latest_split_date = null;

                    foreach ($split_transactions as $split_transaction) {

                        $ratio = $ratios->get($split_transaction->id);

                        if (is_null($ratio)) {

                            $results['skipped']++;

                            continue;
                        }

                        // only transactions recorded when the split transaction was created were
                        // included in it - anything recorded later is already in post-split terms
                        $query = Transaction::where([
                            'portfolio_id' => $split_transaction->portfolio_id,
                            'symbol' => $split_transaction->symbol,
                            'split' => false,
                        ])->whereDate('date', '<', $split_transaction->date->toDateString());

                        if ($split_transaction->created_at) {

                            $query->where(function (Builder $query) use ($split_transaction) {
                                $query->whereNull('created_at')
                                    ->orWhere('created_at', '<=', $split_transaction->created_at);
                            });
                        }

                        self::adjustTransactions($query, $ratio);

                        $split_transaction->deleteQuietly();

                        $latest_split_date = $split_transaction->date;

                        $results['repaired']++;
                    }

                    $holding = Holding::where([
                        'portfolio_id' => $first->portfolio_id,
                        'symbol' => $first->symbol,
                    ])->first();

                    if (is_null($holding) || is_null($latest_split_date)) {

                        return;
                    }

                    // make sure repaired splits are not applied again
                    if (
                        is_null($holding->splits_synced_at)
                        || $holding->splits_synced_at->toDateString() < $latest_split_date->toDateString()
                    ) {
                        $holding->splits_synced_at = now();
                    }

                    $holding->syncTransactionsAndDividends();
                });

                cache()->forget('portfolio-metrics-'.$first->portfolio_id);

                $affected_symbols->push($first->symbol);
            });

        // previously, dividends were never split adjusted
        self::whereNull('dividends_adjusted_at')
            ->get()
            ->each(function (Split $split) use (&$results, $affected_symbols) {

                $split->applyToDividends();

                $results['dividends_adjusted']++;

                $affected_symbols->push($split->symbol);
            });

        $affected_symbols->unique()->each(fn (string $symbol) => Dividend::syncHoldings($symbol));

        return $results;
    }

    /**
     * Number of post-split shares for each pre-split share (i.e. 10 for a 10:1
     * split or 0.1 for a 1:10 reverse split)
     */
    public function shareRatio(): float
    {
        return self::normalizeRatio((float) $this->split_amount);
    }

    /**
     * Split amounts can be stored with limited precision (i.e. 0.3333 for a 1:3 reverse split),
     * so resolve to the simplest ratio that is consistent with that precision
     */
    public static function normalizeRatio(float $split_amount): float
    {
        if ($split_amount <= 0) {

            return $split_amount;
        }

        for ($denominator = 1; $denominator <= self::MAX_RATIO_DENOMINATOR; $denominator++) {

            $numerator = round($split_amount * $denominator);

            if ($numerator > 0 && abs($split_amount - ($numerator / $denominator)) <= self::SPLIT_AMOUNT_PRECISION) {

                return $numerator / $denominator;
            }
        }

        return $split_amount;
    }

    /**
     * Determines the ratio that was used to create a legacy split transaction
     */
    protected static function legacySplitRatio(Transaction $split_transaction): ?float
    {
        $split = self::where(['symbol' => $split_transaction->symbol])
            ->whereDate('date', $split_transaction->date->toDateString())
            ->first();

        if ($split && $split->shareRatio() > 0) {

            return $split->shareRatio();
        }

        // fall back to the quantity owned when the split transaction was created
        $query = Transaction::where([
            'portfolio_id' => $split_transaction->portfolio_id,
            'symbol' => $split_transaction->symbol,
        ])
            ->where('id', '!=', $split_transaction->id)
            ->whereDate('date', '<', $split_transaction->date->toDateString());

        if ($split_transaction->created_at) {

            $query->where(function (Builder $query) use ($split_transaction) {
                $query->whereNull('created_at')
                    ->orWhere('created_at', '<=', $split_transaction->created_at);
            });
        }

        $qty_owned = (float) $query
            ->selectRaw("SUM(CASE WHEN transaction_type = 'BUY' THEN quantity ELSE 0 END) -
                        SUM(CASE WHEN transaction_type = 'SELL' THEN quantity ELSE 0 END) AS qty_owned")
            ->value('qty_owned');

        if ($qty_owned <= 0) {

            return null;
        }

        return self::normalizeRatio(($qty_owned + $split_transaction->quantity) / $qty_owned);
    }

    /**
     * Multiplies quantities by the ratio and divides prices by the ratio
     *
     * @param  Builder<Transaction>  $transactions
     */
    protected static function adjustTransactions(Builder $transactions, float $ratio): void
    {
        $quantity_multiplier = self::toSqlNumber($ratio);
        $price_multiplier = self::toSqlNumber(1 / $ratio);

        $transactions->update([
            'quantity' => DB::raw("quantity * {$quantity_multiplier}"),
            'cost_basis' => DB::raw("cost_basis * {$price_multiplier}"),
            'cost_basis_base' => DB::raw("cost_basis_base * {$price_multiplier}"),
            'sale_price' => DB::raw("sale_price * {$price_multiplier}"),
            'sale_price_base' => DB::raw("sale_price_base * {$price_multiplier}"),
        ]);
    }

    /**
     * Formats a float as a locale independent SQL numeric literal
     */
    protected static function toSqlNumber(float $value): string
    {
        return sprintf('%.15F', $value);
    }
}
