<?php

namespace App\Http\Controllers;

use App\Models\AttendancePhoto;
use App\Services\PhotoService;
use Illuminate\Support\Facades\Gate;

class PhotoController extends Controller
{
    public function __construct(private PhotoService $photoService)
    {
    }

    /** 閲覧は全メンバー可（規約0-6）。ストレージから直接ストリームする */
    public function show(AttendancePhoto $photo)
    {
        Gate::authorize('view', $photo);

        return $this->photoService->fileResponse($photo);
    }

    /** 削除は投稿者本人のみ（spec §5-9・他ユーザーは403） */
    public function destroy(AttendancePhoto $photo)
    {
        Gate::authorize('delete', $photo);

        $this->photoService->delete($photo);

        return back()->with('success', '写真を削除しました');
    }
}
