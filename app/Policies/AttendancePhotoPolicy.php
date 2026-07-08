<?php

namespace App\Policies;

use App\Models\AttendancePhoto;
use App\Models\User;

class AttendancePhotoPolicy
{
    /** 閲覧は全メンバー可（規約0-6） */
    public function view(User $user, AttendancePhoto $photo): bool
    {
        return true;
    }

    /** 削除は投稿者本人のみ（spec §5-9） */
    public function delete(User $user, AttendancePhoto $photo): bool
    {
        return $photo->user_id === $user->id;
    }
}
