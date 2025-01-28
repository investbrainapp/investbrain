<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiChat extends Model
{
    use HasUuids;

    protected $fillable = [
        'role',
        'content',
    ];

    protected $hidden = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($chat) {

            $chat->user_id = auth()->user()->id;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chatable()
    {
        return $this->morphTo();
    }
}
