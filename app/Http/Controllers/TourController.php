<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tour;
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
        ], [
            'name.required' => 'ツアー名を入力してください',
        ]);

        $tour = Tour::create(['name' => $validated['name']]);

        // 作成後はそのツアー詳細（日程0件）へ。続けて日程を追加する導線（spec §5）
        return redirect()->route('tours.show', $tour)
            ->with('success', 'ツアーを作成しました。続けて日程を追加してください');
    }

    /** ツアー詳細（日程一覧）。配下 events を日付順に表示（mockup #scr-event-detail） */
    public function show(Tour $tour)
    {
        $events = $tour->events()
            ->with(['venue', 'setlist'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return view('tours.show', compact('tour', 'events'));
    }

    public function updateDeadlines(Request $request, Tour $tour)
    {
        $validated = $request->validate([
            'events' => ['required', 'array'],
            'events.*.application_deadline' => ['nullable', 'date'],
            'events.*.announce_date' => ['nullable', 'date'],
        ]);

        foreach ($validated['events'] as $eventId => $data) {
            $event = Event::where('tour_id', $tour->id)->find($eventId);
            if (! $event) {
                continue;
            }
            $event->update([
                'application_deadline' => $data['application_deadline'] ?? null,
                'announce_date' => $data['announce_date'] ?? null,
            ]);
        }

        return redirect()->route('tours.show', $tour)
            ->with('success', '締切情報を更新しました');
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
