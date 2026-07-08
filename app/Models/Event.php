<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 公演情報の共有マスタ（spec §4）。
 * user_id を持たない全ユーザー共通マスタ（UserScope を付けない・venues と同型）。
 */
class Event extends Model
{
    protected $fillable = [
        'event_name',
        'event_date',
        'venue_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** 削除可否: 紐づく参戦が1件でもあれば削除不可（venues/グループ削除と同型・spec §5） */
    public function canBeDeleted(): bool
    {
        return ! $this->attendances()->withoutGlobalScopes()->exists();
    }
}
