<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Models\Tour;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LotController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    /**
     * 当落一覧 第1層＝グループカード一覧。
     * 自分の申込があるツアーのidol_group_idからグループを集約する。
     */
    public function index()
    {
        $attendances = Attendance::with(['event.tour.idolGroup', 'fcMemberships'])
            ->whereHas('fcMemberships')
            ->get();

        $toursByGroup = $attendances
            ->filter(fn ($a) => $a->event?->tour)
            ->groupBy(fn ($a) => $a->event->tour->idol_group_id ?? 0)
            ->map(function ($group, $groupId) {
                $tours = $group->groupBy(fn ($a) => $a->event->tour->id);
                $hasPending = $group->contains(
                    fn ($a) => $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending')
                );
                $idolGroup = $groupId ? $group->first()->event->tour->idolGroup : null;
                return (object) [
                    'idol_group' => $idolGroup,
                    'idol_group_id' => $groupId,
                    'tour_count' => $tours->count(),
                    'has_pending' => $hasPending,
                ];
            })
            ->sortBy(fn ($g) => $g->idol_group?->name ?? 'zzz')
            ->values();

        return view('lots.index', compact('toursByGroup'));
    }

    /**
     * 当落 第2層＝グループ内ツアー一覧。
     */
    public function groupTours(\App\Models\IdolGroup $idolGroup = null)
    {
        $query = Attendance::with(['event.tour', 'fcMemberships'])
            ->whereHas('fcMemberships')
            ->whereHas('event.tour', fn ($q) => $idolGroup
                ? $q->where('idol_group_id', $idolGroup->id)
                : $q->whereNull('idol_group_id'));

        $attendances = $query->get();

        $tours = $attendances
            ->filter(fn ($a) => $a->event?->tour)
            ->groupBy(fn ($a) => $a->event->tour->id)
            ->map(function ($group) {
                $tour = $group->first()->event->tour;
                $hasPending = $group->contains(
                    fn ($a) => $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending')
                );
                return (object) [
                    'tour' => $tour,
                    'has_pending' => $hasPending,
                    'latest' => $group->max(fn ($a) => optional($a->event?->event_date)->timestamp),
                ];
            })
            ->sortByDesc('latest')
            ->values();

        return view('lots.group-tours', compact('idolGroup', 'tours'));
    }

    /**
     * 当落詳細（ツアー内の全申込）。配下 events ごとに名義行＋当落セレクト。
     * spec §6「当落画面のツアー単位グルーピング」・mockup #scr-lot-detail。
     */
    public function showByTour(Tour $tour)
    {
        $tour->load('deadlines');

        $attendances = Attendance::with(['event.venue', 'event.tour', 'fcMemberships.person'])
            ->whereHas('fcMemberships')
            ->whereHas('event', fn ($q) => $q->where('tour_id', $tour->id))
            ->orderByEventDateDesc()
            ->get();

        $pending = $attendances->filter(
            fn ($a) => $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending')
        );
        $decided = $attendances->filter(
            fn ($a) => ! $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending')
        );

        return view('lots.show', compact('tour', 'pending', 'decided'));
    }

    /** 申込登録フォーム（S9・v1.5: ツアー→日程のカスケード選択＋名義） */
    public function create()
    {
        $memberships = FcMembership::with('person')->get();
        $tours = Tour::orderByDesc('id')->get(['id', 'name']);
        return view('lots.create', compact('memberships', 'tours'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'identity_id' => ['required', Rule::exists('fc_memberships', 'id')->where('user_id', Auth::id())],
            'companion_id' => ['nullable', Rule::exists('fc_memberships', 'id')->where('user_id', Auth::id())],
        ], [
            'event_id.required' => '日程を選択してください',
            'identity_id.required' => '申込名義を選択してください',
        ]);

        $companion = null;
        if (! empty($validated['companion_id'])) {
            $m = FcMembership::find($validated['companion_id']);
            $companion = $m?->displayName();
        }

        $this->attendanceService->create(
            ['event_id' => $validated['event_id'], 'status' => 'applied', 'companion' => $companion],
            [$validated['identity_id']],
        );

        return redirect()->route('lots.index')
            ->with('success', '申込を登録しました');
    }
}
