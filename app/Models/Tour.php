<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ツアーの共有マスタ（v1.4新設・spec §4）。
 * user_id を持たない全ユーザー共通マスタ（venues/events と同型・UserScope なし）。
 * 単発公演も「1公演だけのツアー」として1件持つ（events.tour_id は必須）。
 */
class Tour extends Model
{
    protected $fillable = [
        'name',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(TourDeadline::class);
    }

    /** 削除可否：紐づく events が1件でもあれば削除不可（events/venues 削除と同型・spec §5） */
    public function canBeDeleted(): bool
    {
        return ! $this->events()->exists();
    }
}
