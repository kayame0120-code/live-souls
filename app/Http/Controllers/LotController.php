<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LotController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    public function index()
    {
        $attendances = Attendance::with(['event.venue', 'fcMemberships.person'])
            ->whereHas('fcMemberships')
            ->orderByEventDateDesc()
            ->get();

        $pending = $attendances->filter(function ($a) {
            return $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending');
        });

        $decided = $attendances->filter(function ($a) {
            return ! $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending');
        });

        return view('lots.index', compact('pending', 'decided'));
    }

    /** 申込登録フォーム（S9）。公演は events 共有マスタから検索付きセレクトで選ぶ */
    public function create()
    {
        $memberships = FcMembership::with('person')->get();
        return view('lots.create', compact('memberships'));
    }

    /**
     * 申込登録: attendances(status=applied) + pivot(result=pending)。
     * 公演は event_id で参照、名義は1つ以上必須（spec §5-7）。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'identity_ids' => ['required', 'array', 'min:1'],
            'identity_ids.*' => [Rule::exists('fc_memberships', 'id')->where('user_id', Auth::id())],
        ], [
            'event_id.required' => '公演を選択してください',
            'identity_ids.required' => '申込名義を選択してください',
            'identity_ids.min' => '申込名義を選択してください',
        ]);

        $this->attendanceService->create(
            ['event_id' => $validated['event_id'], 'status' => 'applied'],
            $validated['identity_ids'],
        );

        return redirect()->route('lots.index')
            ->with('success', '申込を登録しました');
    }
}
