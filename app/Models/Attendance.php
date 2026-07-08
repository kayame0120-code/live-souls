<?php

namespace App\Models;

use App\Models\Scopes\UserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy(UserScope::class)]
class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 会場は event 経由で解決する（v1.2: attendances は venue_id を持たない）。
     * ビュー互換のためアクセサで露出。
     */
    public function getVenueAttribute(): ?Venue
    {
        return $this->event?->venue;
    }

    /**
     * 公演見出し（v1.4: tours.name + event_label を event 経由で解決）。
     * spec §5「公演名表示は tours 経由の参照のみ」。自由記述コピーは持たない。
     */
    public function getEventNameAttribute(): ?string
    {
        return $this->event?->displayName();
    }

    /** 公演日（event 経由・ビュー互換アクセサ） */
    public function getEventDateAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->event?->event_date;
    }

    /*
    |--------------------------------------------------------------------------
    | 公演日は events 側にあるため、日付での並び替え・絞り込みは event 経由で行う
    |--------------------------------------------------------------------------
    */

    /** 公演日の降順（events.event_date 相関サブクエリ・DB方言に依存しない） */
    public function scopeOrderByEventDateDesc(Builder $query): Builder
    {
        return $query->orderByDesc(
            Event::select('event_date')->whereColumn('events.id', 'attendances.event_id')
        );
    }

    /** 指定年の公演のみ（events.event_date） */
    public function scopeForEventYear(Builder $query, string $year): Builder
    {
        return $query->whereHas('event', fn (Builder $e) => $e->whereYear('event_date', $year));
    }

    /** 公演日が指定日以降（今日含む・カラム比較はevents側） */
    public function scopeEventDateFrom(Builder $query, $date): Builder
    {
        return $query->whereHas('event', fn (Builder $e) => $e->whereDate('event_date', '>=', $date));
    }

    /** 公演日が指定日以前（今日含む） */
    public function scopeEventDateUntil(Builder $query, $date): Builder
    {
        return $query->whereHas('event', fn (Builder $e) => $e->whereDate('event_date', '<=', $date));
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
