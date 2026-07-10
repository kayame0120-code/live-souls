<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdolGroup extends Model
{
    protected $fillable = ['name', 'status'];

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'idol_group_id')->orderBy('sort_order');
    }
}
