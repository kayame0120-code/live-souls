<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ScopedBy(UserScope::class)]
class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'venue_id',
        'event_name',
        'event_date',
        'open_time',
        'start_time',
        'seat_raw',
        'seat_block',
        'seat_row',
        'seat_number',
        'status',
        'companion',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function fcMemberships(): BelongsToMany
    {
        return $this->belongsToMany(FcMembership::class, 'attendance_identity')
            ->withPivot(['result', 'ticket_count', 'id'])
            ->withTimestamps();
    }
}
