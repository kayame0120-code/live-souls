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
     * 当落一覧＝ツアーカード（v1.4・spec §5「当落画面のツアー単位グルーピング」）。
     * attendances.event_id → events.tour_id を辿り、自分の申込があるツアーだけをまとめる。
     * 配下に pending を含めば「当落待ちあり」、無ければ「発表済」。
     */
    public function index()
    {
        $attendances = Attendance::with(['event.tour', 'fcMemberships'])
            ->whereHas('fcMemberships')
            ->get();

        // ツアー単位でグルーピング（新テーブル不要・表示ロジックのみ）
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

        return view('lots.index', compact('tours'));
    }

    /**
     * 当落詳細（ツアー内の全申込）。配下 events ごとに名義行＋当落セレクト。
     * spec §6「当落画面のツアー単位グルーピング」・mockup #scr-lot-detail。
     */
    public function showByTour(Tour $tour)
    {
        // このツアー配下の event を指す自分の申込（fcMemberships付き）
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

    /**
     * 申込登録: attendances(status=applied) + pivot(result=pending)。
     * v1.5: 当落ステータスは作成時に選ばせない（pending 自動生成）。名義は1つ以上必須。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'identity_ids' => ['required', 'array', 'min:1'],
            'identity_ids.*' => [Rule::exists('fc_memberships', 'id')->where('user_id', Auth::id())],
        ], [
            'event_id.required' => '日程を選択してください',
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
