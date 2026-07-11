<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tour;
use App\Models\TourDeadline;
use Illuminate\Http\Request;

/**
 * ツアーの共有マスタ（v1.4新設・spec §3/§5）。
 * 全ユーザー読取／追加／編集可・user_idスコープなし（マルチテナント例外）。
 * 削除は紐づく events 0件時のみ。
 */
class TourController extends Controller
{
    public function create()
    {
        return view('tours.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'idol_group_id' => ['nullable', 'exists:idol_groups,id'],
        ], [
            'name.required' => 'ツアー名を入力してください',
        ]);

        $tour = Tour::create([
            'name' => $validated['name'],
            'idol_group_id' => $validated['idol_group_id'] ?? null,
        ]);

        // 作成後はそのツアー詳細（日程0件）へ。続けて日程を追加する導線（spec §5）
        return redirect()->route('tours.show', $tour)
            ->with('success', 'ツアーを作成しました。続けて日程を追加してください');
    }

    /** ツアー詳細（日程一覧）。配下 events を日付順に表示（mockup #scr-event-detail） */
    public function show(Tour $tour)
    {
        $tour->load('deadlines');
        $events = $tour->events()
            ->with(['venue'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return view('tours.show', compact('tour', 'events'));
    }

    public function updateDeadlines(Request $request, Tour $tour)
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'application_deadline' => ['nullable', 'date'],
            'announce_date' => ['nullable', 'date'],
        ]);

        if (empty($validated['application_deadline']) && empty($validated['announce_date'])) {
            return back()->with('error', '締切または発表日を入力してください');
        }

        $tour->deadlines()->create([
            'label' => $validated['label'] ?? null,
            'application_deadline' => $validated['application_deadline'] ?? null,
            'announce_date' => $validated['announce_date'] ?? null,
        ]);

        return redirect()->route('tours.show', $tour)
            ->with('success', '締切を追加しました');
    }

    public function updateDeadline(Request $request, Tour $tour, TourDeadline $deadline)
    {
        if ($deadline->tour_id !== $tour->id) {
            abort(404);
        }

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'application_deadline' => ['nullable', 'date'],
            'announce_date' => ['nullable', 'date'],
        ]);

        $deadline->update($validated);

        return redirect()->route('tours.show', $tour)
            ->with('success', '締切を更新しました');
    }

    public function destroyDeadline(Tour $tour, TourDeadline $deadline)
    {
        if ($deadline->tour_id !== $tour->id) {
            abort(404);
        }

        $deadline->delete();

        return redirect()->route('tours.show', $tour)
            ->with('success', '締切を削除しました');
    }

    public function updateGroup(Request $request, Tour $tour)
    {
        $validated = $request->validate([
            'idol_group_id' => ['nullable', 'exists:idol_groups,id'],
        ]);

        $previousGroupId = $tour->idol_group_id;
        $tour->update(['idol_group_id' => $validated['idol_group_id'] ?: null]);

        $redirect = $previousGroupId
            ? redirect()->route('events.group-tours', $previousGroupId)
            : redirect()->route('events.uncategorized');

        return $redirect->with('success', '所属グループを変更しました');
    }

    public function destroy(Tour $tour)
    {
        // 紐づく日程（events）がある場合は削除不可（events/venues 削除と同型）
        if (! $tour->canBeDeleted()) {
            return back()->with('error', 'このツアーには日程が登録されているため削除できません');
        }

        $tour->delete();

        return redirect()->route('events.index')
            ->with('success', 'ツアーを削除しました');
    }
}
