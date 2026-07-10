<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 公演情報の共有マスタ（spec §4）。
 * user_id を持たない全ユーザー共通マスタ（UserScope を付けない・venues と同型）。
 */
class Event extends Model
{
    protected $fillable = [
        'tour_id',
        'event_label',
        'event_date',
        'start_time',
        'application_deadline',
        'announce_date',
        'venue_id',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'application_deadline' => 'datetime',
            'announce_date' => 'date',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * 公演見出し（spec §4「表示の組み立て」・v1.5「tours経由参照のみ」）。
     * tours.name（＋event_label があれば結合）。公演名の自由記述コピーは持たない。
     */
    public function displayName(): string
    {
        $name = $this->tour?->name ?? '';
        if ($this->event_label) {
            return trim($name . ' ' . $this->event_label);
        }
        return $name;
    }

    /**
     * 開演時間（QV13-5）。列型は time。
     * 指示書の `datetime:H:i` キャストは保存時に `Y-m-d H:i:s` を time 列へ入れてしまい
     * SQLiteの whereTime 突合ズレ・PostgreSQLの INSERT 失敗を招くため、
     * 「保存は H:i:s のクリーンな時刻文字列／取得は Carbon」で往復するアクセサに差し替える。
     * これにより表示側の ->format('H:i') はそのまま使え、time 列にも安全に収まる。
     */
    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null || $value === '' ? null : Carbon::parse($value),
            set: fn ($value) => $value === null || $value === '' ? null : Carbon::parse($value)->format('H:i:s'),
        );
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** 申込締切を過ぎているか（サーバー側now()判定・spec §4.6） */
    public function isDeadlinePassed(): bool
    {
        return $this->application_deadline !== null && now()->gte($this->application_deadline);
    }

    public function setlist(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Setlist::class);
    }

    /** 削除可否: 紐づく参戦が1件でもあれば削除不可（venues/グループ削除と同型・spec §5） */
    public function canBeDeleted(): bool
    {
        return ! $this->attendances()->withoutGlobalScopes()->exists();
    }
}
