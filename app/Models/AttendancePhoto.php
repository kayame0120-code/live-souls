<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 参戦写真。閲覧はメンバー間共有（規約0-6の例外②）のため
 * グローバルuser_idスコープを適用しない。書込・削除は投稿者のみ（Policy）。
 */
class AttendancePhoto extends Model
{
    protected $fillable = [
        'attendance_id',
        'user_id',
        'path',
        'caption',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
