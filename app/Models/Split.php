<?php

namespace App\Models;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Interfaces\MarketData\MarketDataInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Split extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'symbol',
        'date',
        'split_amount',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'datetime',
        'first_date' => 'datetime',
        'last_date' => 'datetime',
    ];

    /**
     * Syncs all transactions of symbol with split data
     *
     * @param string $symbol
     * @return void
     */
    public static function syncToTransactions($symbol) 
    {
        // get relevant split data
        $splits = self::where([
                'splits.symbol' => $symbol,
            ])
            ->whereDate('transactions.date', '>', DB::raw('IFNULL(market_data.splits_synced_to_holdings_at, "0000-00-00")'))
            ->select([
                'splits.date', 
                'splits.symbol', 
                'splits.split_amount', 
                'transactions.portfolio_id'
            ])
            ->join('transactions', 'transactions.symbol', 'splits.symbol')
            ->join('market_data', 'transactions.symbol', 'market_data.symbol')
            ->orderBy('splits.date', 'ASC')
            ->get();

        foreach($splits as $split) {

            $qty_owned = Transaction::where([
                    'symbol' => $split->symbol, 
                    'portfolio_id' => $split->portfolio_id
                ])
                ->whereDate('transactions.date', '<', $split->date->format('Y-m-d'))
                ->sum('quantity');

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
                    'updated_at' => now()
                ]);
            }
        }

        // update market data with latest date
        MarketData::setSplitsHoldingSynced($symbol);
    }

    /**
     * Grab new split data
     *
     * @param string $symbol
     * @param \DateTimeInterface|null $start_date
     * @return void
     */
    public static function getSplitData(string $symbol) 
    {
        // dates for split data
        $splits_meta = self::where(['symbol' => $symbol])
            ->selectRaw('COUNT(symbol) as total_splits')
            ->selectRaw('MIN(date) as first_date')
            ->selectRaw('MAX(date) as last_date')
            ->get()
            ->first();

        // assume need to populate all split data because it didnt exist before
        $start_date = new \DateTime('@0');
        $end_date = now();

        // nope, need to populate newer split data
        if ($splits_meta->total_splits) {
            
            $start_date = $splits_meta->last_date->addHours(48);
            $end_date =  now();
        }

        // get some data
        if ($split_data = collect() && $start_date && $end_date) {
            $split_data = app(MarketDataInterface::class)->splits($symbol, $start_date, $end_date);
        }

        if ($split_data->isNotEmpty()) {
            // insert records
            (new self)->insert($split_data->toArray());   
            
        }

        // sync to transactions
        self::syncToTransactions($symbol);

        return $split_data;
    }

    public function holdings() {
        return $this->hasMany(Holding::class, 'symbol', 'symbol');
    }

    public function transactions() {
        return $this->hasMany(Transaction::class, 'symbol', 'symbol');
    }
}
