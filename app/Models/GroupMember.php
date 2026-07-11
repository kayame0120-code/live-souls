<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    protected $fillable = [
        'idol_group_id',
        'name',
        'color_name',
        'color_hex',
        'source_type',
        'sort_order',
    ];

    public function idolGroup(): BelongsTo
    {
        return $this->belongsTo(IdolGroup::class);
    }
}
