<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\MarketData\MarketDataInterface;
use App\Traits\HasMarketData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Split extends Model
{
    use HasFactory;
    use HasMarketData;
    use HasUuids;

    protected $fillable = [
        'symbol',
        'date',
        'split_amount',
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'last_date' => 'datetime',
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
     * @param  \DateTimeInterface|null  $start_date
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

        if ($split_data->isNotEmpty()) {

            // insert records
            (new self)->insert($split_data->map(function ($split) {

                return [...$split, ...['id' => Str::uuid()->toString()]];
            })->toArray());
        }

        // sync to transactions
        self::syncToTransactions($symbol);
    }

    /**
     * Syncs all transactions of symbol with split data
     *
     * @param  string  $symbol
     * @return void
     */
    public static function syncToTransactions($symbol)
    {
        // get splits joined with matching holdings
        $splits = self::select([
            'splits.date',
            'splits.symbol',
            'splits.split_amount',
            'holdings.portfolio_id',
        ])
            ->where([
                'splits.symbol' => $symbol,
            ])
            ->whereDate('splits.date', '>', DB::raw("COALESCE(holdings.splits_synced_at, '1901-01-01')"))
            ->where('holdings.quantity', '>', 0)
            ->join('holdings', 'splits.symbol', 'holdings.symbol')
            ->orderBy('splits.date', 'ASC')
            ->get();

        foreach ($splits as $split) {

            // get qty owned when split was issued
            $qty_owned = Transaction::where([
                'symbol' => $split->symbol,
                'portfolio_id' => $split->portfolio_id,
            ])
                ->whereDate('transactions.date', '<', $split->date->toDateString())
                ->selectRaw("SUM(CASE WHEN transaction_type = 'BUY' THEN quantity ELSE 0 END) -
                            SUM(CASE WHEN transaction_type = 'SELL' THEN quantity ELSE 0 END) AS qty_owned")
                ->value('qty_owned');

            if ($qty_owned > 0) {

                Transaction::create([
                    'symbol' => $split->symbol,
                    'portfolio_id' => $split->portfolio_id,
                    'transaction_type' => 'BUY',
                    'date' => $split->date,
                    'quantity' => ($qty_owned * $split->split_amount) - $qty_owned,
                    'cost_basis' => 0,
                    'split' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Holding::where([
                    'symbol' => $split->symbol,
                    'portfolio_id' => $split->portfolio_id,
                ])->update([
                    'splits_synced_at' => now(),
                ]);
            }
        }
    }
}
