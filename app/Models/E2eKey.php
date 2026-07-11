<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class E2eKey extends Model
{
    protected $fillable = [
        'user_id',
        'wrapped_master_key_pw',
        'pw_salt',
        'wrapped_master_key_rk',
        'rk_salt',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
