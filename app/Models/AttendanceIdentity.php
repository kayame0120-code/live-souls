<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceIdentity extends Model
{
    protected $table = 'attendance_identity';

    protected $fillable = [
        'attendance_id',
        'fc_membership_id',
        'result',
        'ticket_count',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function fcMembership(): BelongsTo
    {
        return $this->belongsTo(FcMembership::class);
    }
}
