<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ConnectedAccountVerification extends Model
{
    
    use HasUuids;

    protected $fillable = [
        'provider',
        'provider_id',
        'email',
        'connected_account'
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'verified_at' => 'datetime',
            'connected_account' => 'json'
        ];
    }
}
