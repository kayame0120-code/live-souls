<?php

namespace App\Http\Controllers;

use App\Contracts\LlmService;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DeadlineController extends Controller
{
    public function __construct(private LlmService $llm)
    {
    }

    public function form()
    {
        return view('deadlines.form');
    }

    public function parse(Request $request)
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ], [
            'text.required' => 'テキストを貼り付けてください',
        ]);

        try {
            $result = $this->llm->parseDeadlines($validated['text']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'AI解析に失敗しました: ' . $e->getMessage());
        }

        $deadlines = $result['deadlines'] ?? [];
        $events = Event::with(['venue', 'tour'])->orderBy('event_date')->get();

        $rows = collect($deadlines)->map(function ($d) use ($events) {
            $matched = $events->first(function ($e) use ($d) {
                $venueMatch = $e->venue && mb_strpos($e->venue->name, $d['venue'] ?? '') !== false;
                $dateMatch = ! empty($d['event_date']) && $e->event_date->format('Y-m-d') === $d['event_date'];
                return $venueMatch && $dateMatch;
            });

            return [
                'venue' => $d['venue'] ?? '',
                'event_date' => $d['event_date'] ?? '',
                'application_deadline' => $d['application_deadline'] ?? '',
                'announce_date' => $d['announce_date'] ?? '',
                'matched_event_id' => $matched?->id,
                'matched_label' => $matched ? ($matched->displayName() . ' ' . $matched->event_date->format('m.d') . ' ' . ($matched->venue?->name ?? '')) : null,
            ];
        })->all();

        return view('deadlines.confirm', compact('rows', 'events'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.include' => ['nullable'],
            'rows.*.event_id' => ['nullable', 'exists:events,id'],
            'rows.*.application_deadline' => ['nullable', 'date'],
            'rows.*.announce_date' => ['nullable', 'date'],
        ]);

        $updated = 0;

        foreach ($validated['rows'] as $row) {
            if (empty($row['include']) || empty($row['event_id'])) {
                continue;
            }
            $event = Event::find($row['event_id']);
            if (! $event) {
                continue;
            }

            $event->update([
                'application_deadline' => $row['application_deadline'] ?? null,
                'announce_date' => $row['announce_date'] ?? null,
            ]);
            $updated++;
        }

        if ($updated === 0) {
            return back()->with('error', '更新対象がありませんでした');
        }

        return redirect()->route('lots.index')
            ->with('success', "{$updated}件の締切情報を更新しました");
    }
}
