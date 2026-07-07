<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Venue;
use App\Models\VenueNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VenueController extends Controller
{
    public function show(Venue $venue)
    {
        $note = $venue->noteForUser(Auth::id());
        $attendances = Attendance::with('fcMemberships.person')
            ->where('venue_id', $venue->id)
            ->orderByDesc('event_date')
            ->get();

        return view('venues.show', compact('venue', 'note', 'attendances'));
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
}
