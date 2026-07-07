<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceIdentity;
use Illuminate\Support\Facades\Auth;

class LotController extends Controller
{
    public function index()
    {
        $attendances = Attendance::with(['venue', 'fcMemberships.person'])
            ->whereHas('fcMemberships')
            ->orderByDesc('event_date')
            ->get();

        $pending = $attendances->filter(function ($a) {
            return $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending');
        });

        $decided = $attendances->filter(function ($a) {
            return ! $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending');
        });

        return view('lots.index', compact('pending', 'decided'));
    }
}
