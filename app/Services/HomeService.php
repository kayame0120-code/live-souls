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
        return Attendance::with(['venue', 'fcMemberships.person'])
            ->where('event_date', '>=', Carbon::today())
            ->whereIn('status', ['planned', 'applied'])
            ->orderBy('event_date')
            ->first();
    }

    public function getStats(): array
    {
        $year = Carbon::now()->year;

        $attendedCount = Attendance::where('status', '!=', 'skipped')
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
            ->where('status', '!=', 'skipped')
            ->orderByDesc('event_date')
            ->limit($limit)
            ->get();
    }
}
