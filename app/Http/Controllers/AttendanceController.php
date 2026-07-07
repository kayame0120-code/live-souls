<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceIdentity;
use App\Models\FcMembership;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $service)
    {
    }

    public function index(Request $request)
    {
        $year = $request->get('year');
        $query = Attendance::with(['venue', 'fcMemberships.person'])
            ->orderByDesc('event_date');

        if ($year && $year !== 'all') {
            $query->whereYear('event_date', $year);
        }

        $attendances = $query->get();

        $years = Attendance::pluck('event_date')
            ->map(fn ($d) => $d->format('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if (empty($years)) {
            $years = [(string) now()->year];
        }

        return view('attendances.index', compact('attendances', 'years', 'year'));
    }

    public function create()
    {
        $memberships = FcMembership::with('person')->get();
        return view('attendances.create', compact('memberships'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'seat_raw' => ['nullable', 'string', 'max:255'],
            'seat_block' => ['nullable', 'string', 'max:255'],
            'seat_row' => ['nullable', 'string', 'max:255'],
            'seat_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:applied,planned,attended,skipped'],
            'companion' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string'],
            'identity_ids' => ['nullable', 'array'],
            'identity_ids.*' => ['exists:fc_memberships,id'],
        ]);

        $identityIds = $validated['identity_ids'] ?? [];
        $this->service->create($validated, $identityIds);

        return redirect()->route('attendances.index')
            ->with('success', '参戦記録を保存しました');
    }

    public function show(Attendance $attendance)
    {
        $attendance->load(['venue', 'fcMemberships.person']);
        return view('attendances.show', compact('attendance'));
    }

    public function edit(Attendance $attendance)
    {
        $attendance->load('fcMemberships');
        $memberships = FcMembership::with('person')->get();
        return view('attendances.edit', compact('attendance', 'memberships'));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'seat_raw' => ['nullable', 'string', 'max:255'],
            'seat_block' => ['nullable', 'string', 'max:255'],
            'seat_row' => ['nullable', 'string', 'max:255'],
            'seat_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:applied,planned,attended,skipped'],
            'companion' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string'],
            'identity_ids' => ['nullable', 'array'],
            'identity_ids.*' => ['exists:fc_memberships,id'],
        ]);

        $identityIds = $validated['identity_ids'] ?? [];
        $this->service->update($attendance, $validated, $identityIds);

        return redirect()->route('attendances.show', $attendance)
            ->with('success', '参戦記録を更新しました');
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return redirect()->route('attendances.index')
            ->with('success', '参戦記録を削除しました');
    }
}
