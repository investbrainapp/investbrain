<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyChange extends Model
{
    use HasCompositePrimaryKey, HasFactory;

    public $timestamps = false;

    protected $primaryKey = ['date', 'portfolio_id'];

    protected $table = 'daily_change';

    protected $fillable = [
        'portfolio_id',
        'date',
        'total_market_value',
        'total_cost_basis',
        'total_gain',
        'total_dividends_earned',
        'realized_gains',
        'notes',
    ];

    protected $hidden = [];

    protected $casts = [
        'date' => 'datetime',
        'total_market_value' => 'float',
        'total_cost_basis' => 'float',
        'total_gain' => 'float',
        'total_dividends_earned' => 'float',
        'realized_gains' => 'float',
    ];

    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('daily_change.portfolio_id', $portfolio);
    }

    public function scopeMyDailyChanges()
    {
        return $this->whereHas('portfolio', function ($query) {
            $query->whereHas('users', function ($query) {
                return $query->where('id', auth()->id());
            });
        });
    }

    public function scopeWithoutWishlists($query)
    {
        return $query->whereHas('portfolio', function ($query) {
            $query->where('portfolios.wishlist', 0);
        });
    }

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
}
