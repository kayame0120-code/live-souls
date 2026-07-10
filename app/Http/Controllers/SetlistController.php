<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Setlist;
use App\Models\SetlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetlistController extends Controller
{
    public function show(Event $event)
    {
        $setlist = $event->setlist?->load('items');

        return view('setlists.show', compact('event', 'setlist'));
    }

    public function addItem(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'display_label' => ['nullable', 'string', 'max:50'],
        ]);

        $setlist = $event->setlist ?? Setlist::create(['event_id' => $event->id]);

        $maxOrder = $setlist->items()->max('sort_order') ?? 0;

        $setlist->items()->create([
            'sort_order' => $maxOrder + 1,
            'display_label' => $validated['display_label'] ?? null,
            'title' => $validated['title'],
        ]);

        return redirect()->route('setlists.show', $event)
            ->with('success', '曲を追加しました');
    }

    public function destroyItem(Event $event, SetlistItem $item)
    {
        if ($item->setlist->event_id !== $event->id) {
            abort(404);
        }

        $item->delete();

        return redirect()->route('setlists.show', $event)
            ->with('success', '曲を削除しました');
    }

    public function bulkStore(Request $request, Event $event)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.display_label' => ['nullable', 'string', 'max:50'],
            'items.*.include' => ['nullable'],
        ]);

        $setlist = $event->setlist ?? Setlist::create(['event_id' => $event->id]);
        $maxOrder = $setlist->items()->max('sort_order') ?? 0;
        $count = 0;

        DB::transaction(function () use ($validated, $setlist, &$maxOrder, &$count) {
            foreach ($validated['items'] as $item) {
                if (empty($item['include'])) {
                    continue;
                }
                $setlist->items()->create([
                    'sort_order' => ++$maxOrder,
                    'display_label' => $item['display_label'] ?? null,
                    'title' => $item['title'],
                ]);
                $count++;
            }
        });

        return redirect()->route('setlists.show', $event)
            ->with('success', "{$count}曲を登録しました");
    }
}
