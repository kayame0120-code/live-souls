<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\Scopes\UserScope;
use App\Models\Venue;
use App\Models\VenueNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class VenueController extends Controller
{
    public function show(Venue $venue)
    {
        $note = $venue->noteForUser(Auth::id());

        // 会場は event 経由で解決する（v1.2: attendances に venue_id なし）。自分の参戦のみ
        $attendances = Attendance::with(['event.venue', 'fcMemberships.person'])
            ->whereHas('event', fn ($e) => $e->where('venue_id', $venue->id))
            ->orderByEventDateDesc()
            ->get();

        // 見え方マッピング（spec §5-9）: 全メンバーの写真を座席情報つきで表示（規約0-6の例外③）
        $photos = AttendancePhoto::with(['user'])
            ->whereHas('attendance', function ($q) use ($venue) {
                $q->withoutGlobalScope(UserScope::class)
                    ->whereHas('event', fn ($e) => $e->where('venue_id', $venue->id));
            })
            ->get()
            ->each(function ($photo) {
                $photo->setRelation(
                    'attendance',
                    Attendance::withoutGlobalScope(UserScope::class)
                        ->with('event')
                        ->find($photo->attendance_id),
                );
            });

        return view('venues.show', compact('venue', 'note', 'attendances', 'photos'));
    }

    public function updateNote(Request $request, Venue $venue)
    {
        $validated = $request->validate([
            'lodging' => ['nullable', 'string', 'max:255'],
            'transport_cost' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string'],
        ]);

        VenueNote::updateOrCreate(
            ['user_id' => Auth::id(), 'venue_id' => $venue->id],
            $validated,
        );

        return redirect()->route('venues.show', $venue)
            ->with('success', 'メモを保存しました');
    }

    public function suggest(Request $request)
    {
        $q = $request->get('q', '');
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $venues = Venue::where('name', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'name', 'address']);

        return response()->json($venues);
    }

    /**
     * Places API 会場オートフィル（spec §5-11 [既定]）。
     * キー未設定・API失敗時は空を返し手入力にフォールバック（会場登録をブロックしない）。
     * レスポンスは永続キャッシュしない（規約0-7）。
     */
    public function placeLookup(Request $request)
    {
        $q = $request->get('q', '');
        $key = config('services.google_places.key');

        if (! $key || mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress',
                ])
                ->post('https://places.googleapis.com/v1/places:searchText', [
                    'textQuery' => $q,
                    'languageCode' => 'ja',
                ]);

            if (! $response->successful()) {
                return response()->json(['results' => []]);
            }

            $results = collect($response->json('places', []))
                ->map(fn ($p) => [
                    'name' => $p['displayName']['text'] ?? '',
                    'address' => $p['formattedAddress'] ?? '',
                ])
                ->take(5)
                ->values();

            return response()->json(['results' => $results]);
        } catch (\Throwable) {
            // API失敗は手入力フォールバック（spec §7）
            return response()->json(['results' => []]);
        }
    }
}
