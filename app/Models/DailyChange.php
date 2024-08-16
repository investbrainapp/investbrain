<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyChange extends Model
{
    use HasFactory, HasCompositePrimaryKey;

    public $timestamps = false;

    /**
     * The primary key of the table.
     * 
     * @var string
     */
    protected $primaryKey = ['date', 'portfolio_id'];

    /**
     * Table name for the model
     *
     * @var string
     */
    protected $table = 'daily_change';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'portfolio_id',
        'date',
        'total_market_value',
        'total_cost_basis',
        'total_gain_loss',
        'total_dividends',
        'realized_gains',
        'notes',
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
    ];

    public function scopeMyDailyChanges($query)
    {
        return $query->where('user_id', auth()->user()->id);
    }

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }

}
