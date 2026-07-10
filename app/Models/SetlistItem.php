<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetlistItem extends Model
{
    protected $fillable = [
        'setlist_id',
        'sort_order',
        'display_label',
        'title',
    ];

    public function setlist(): BelongsTo
    {
        return $this->belongsTo(Setlist::class);
    }
}
