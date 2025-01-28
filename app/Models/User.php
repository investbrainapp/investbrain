<?php

namespace App\Models;

use App\Traits\HasConnectedAccounts;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasConnectedAccounts;
    use HasFactory;
    use HasProfilePhoto;
    use HasRelationships;
    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'admin',
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function portfolios()
    {
        return $this->belongsToMany(Portfolio::class)->withPivot(['owner', 'full_access', 'invite_accepted_at']);
    }

    public function daily_changes()
    {
        return $this->hasManyDeep(DailyChange::class, ['portfolio_user', Portfolio::class]);
    }

    public function holdings(): HasManyDeep
    {
        return $this->hasManyDeep(Holding::class, ['portfolio_user', Portfolio::class])
            ->withMarketData()
            ->withPerformance();
    }

    public function transactions(): HasManyDeep
    {
        return $this->hasManyDeep(Transaction::class, ['portfolio_user', Portfolio::class])
            ->withMarketData()
            ->withAggregate('portfolio', 'title')
            ->selectRaw('
                CASE
                    WHEN transaction_type = \'SELL\' 
                    THEN COALESCE(transactions.sale_price - transactions.cost_basis, 0)
                    ELSE COALESCE(market_data.market_value - transactions.cost_basis, 0)
                END AS gain_dollars');
    }
}
