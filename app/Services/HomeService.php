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
        // applied は当落待ちであり確定した現場ではない（spec §5-7）
        return Attendance::with(['venue', 'fcMemberships.person'])
            ->where('event_date', '>=', Carbon::today())
            ->whereIn('status', ['planned', 'attended'])
            ->orderBy('event_date')
            ->first();
    }

    public function getStats(): array
    {
        $year = Carbon::now()->year;

        // skipped と applied（未確定の申込）は「今年の参戦」に数えない
        $attendedCount = Attendance::whereNotIn('status', ['skipped', 'applied'])
            ->whereYear('event_date', $year)
            ->where('event_date', '<', Carbon::today()->addDay())
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
        return Attendance::with(['venue', 'fcMemberships.person'])
            ->where('event_date', '<=', Carbon::today())
            ->whereNotIn('status', ['skipped', 'applied'])
            ->orderByDesc('event_date')
            ->limit($limit)
            ->get();
    }
}
