<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function photos(): HasMany
    {
        return $this->hasMany(AttendancePhoto::class);
    }

    /**
     * 削除可否（spec §7 Q3確定）:
     * won のpivotが1件も無ければ削除可（status=applied / 全pending・lost / 一般参戦を含む）。
     * won付き（昇格済み）は当選履歴保全のため削除不可 → skippedへの変更で対応。
     */
    public function canBeDeleted(): bool
    {
        return ! $this->fcMemberships()->wherePivot('result', 'won')->exists();
    }

    /**
     * 座席の自動合成（spec §5-8）: 「{block} {row}列 {number}番」空要素はスキップ。
     */
    public static function composeSeatRaw(?string $block, ?string $row, ?string $number): ?string
    {
        $parts = array_filter([
            $block,
            $row !== null && $row !== '' ? "{$row}列" : null,
            $number !== null && $number !== '' ? "{$number}番" : null,
        ], fn ($p) => $p !== null && $p !== '');

        return $parts ? implode(' ', $parts) : null;
    }
}
