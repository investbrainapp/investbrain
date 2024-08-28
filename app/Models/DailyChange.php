<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyChange extends Model
{
    use HasFactory, HasCompositePrimaryKey;

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
    ];
    
    public function scopePortfolio($query, $portfolio)
    {
        return $query->where('portfolio_id', $portfolio);
    }
    
    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }
}
