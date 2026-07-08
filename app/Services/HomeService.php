<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceIdentity;
use App\Models\FcMembership;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class HomeService
{
    public function getNextAttendance(): ?Attendance
    {
        // applied は当落待ちであり確定した現場ではない（spec §5-7）。日付・会場は event 経由
        return Attendance::with(['event.tour', 'event.venue', 'fcMemberships.person'])
            ->whereIn('status', ['planned', 'attended'])
            ->eventDateFrom(Carbon::today())
            ->orderByEventDateDesc() // 直近未来を先頭にするため後で反転
            ->get()
            ->sortBy(fn ($a) => optional($a->event?->event_date)->timestamp)
            ->first();
    }

    public function getStats(): array
    {
        $year = (string) Carbon::now()->year;

        // skipped と applied（未確定の申込）は「今年の参戦」に数えない
        $attendedCount = Attendance::whereNotIn('status', ['skipped', 'applied'])
            ->forEventYear($year)
            ->eventDateUntil(Carbon::today())
            ->count();

        $pendingLots = AttendanceIdentity::whereHas('attendance', function ($q) {
            $q->where('user_id', Auth::id());
        })->where('result', 'pending')->count();

        $identityCount = FcMembership::count();

        return [
            'attended_count' => $attendedCount,
            'pending_lots' => $pendingLots,
            'identity_count' => $identityCount,
        ];
    }

    public function getRecentAttendances(int $limit = 3)
    {
        return Attendance::with(['event.tour', 'event.venue', 'fcMemberships.person'])
            ->whereNotIn('status', ['skipped', 'applied'])
            ->eventDateUntil(Carbon::today())
            ->orderByEventDateDesc()
            ->limit($limit)
            ->get();
    }

    /**
     * 公演日を過ぎた「参戦予定（planned）」の確認対象（spec §5・T8）。
     * ホームで「参戦した？」を表示し、確定操作で attended へ。自動遷移はしない。
     */
    public function getPendingConfirmations()
    {
        return Attendance::with(['event.tour', 'event.venue', 'fcMemberships.person'])
            ->where('status', 'planned')
            ->whereHas('event', fn ($e) => $e->whereDate('event_date', '<', Carbon::today()))
            ->orderByEventDateDesc()
            ->get();
    }
}
