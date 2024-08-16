<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Portfolio extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'notes',
        'wishlist',
    ];

     /**
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($model) {
            self::syncUsers($model);
        });
    }

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
        'wishlist' => 'boolean'
    ];

    /**
     * The relationships that should always be eagerly loaded.
     *
     * @var array
     */
    protected $with = ['users', 'transactions'];

    /**
     * The attributes that should be appended.
     *
     * @var array
     */
    protected $appends = ['owner_id'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('owner');
    }

    public function holdings()
    {
        return $this->hasMany(Holding::class, 'portfolio_id', 'id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function daily_change()
    {
        return $this->hasMany(DailyChange::class);
    }

    public function scopeMyPortfolios()
    {
        return $this->whereRelation('users', 'id', auth()->user()->id);
    }

    public function scopeWithoutWishlists() 
    {
        return $this->where(['wishlist' => false]);
    }

    public function getOwnerIdAttribute()
    {
        return $this->users()->firstWhere('owner', 1)?->id;
    }

    public static function syncUsers(self $model) {
        // make sure we don't remove owner access
        $user_id[$model->owner_id ?? auth()->user()->id] = ['owner' => true];

        // // add other users
        // foreach(request()->users ?? [] as $id) {
        //     $user_id[$id] = ['owner' => false];
        // };

        // save
        $model->users()->sync($user_id);
    }
}
