<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'address',
        'nearest_station',
        'capacity',
        'arena_view_key',
        'created_by',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function noteForUser(int $userId): ?VenueNote
    {
        return $this->notes()->where('user_id', $userId)->first();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(VenueNote::class);
    }
}
