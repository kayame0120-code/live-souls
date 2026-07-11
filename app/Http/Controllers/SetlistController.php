<?php

namespace App\Http\Controllers;

use App\Contracts\LlmService;
use App\Models\Setlist;
use App\Models\SetlistItem;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetlistController extends Controller
{
    public function __construct(private LlmService $llm)
    {
    }

    public function show(Tour $tour)
    {
        $tour->load('setlists.items');

        return view('setlists.show', compact('tour'));
    }

    public function addItem(Request $request, Tour $tour)
    {
        $validated = $request->validate([
            'setlist_id' => ['nullable', 'exists:setlists,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'display_label' => ['nullable', 'string', 'max:50'],
        ]);

        $setlist = isset($validated['setlist_id'])
            ? $tour->setlists()->findOrFail($validated['setlist_id'])
            : $tour->setlists()->create(['label' => $validated['label'] ?? null]);

        $maxOrder = $setlist->items()->max('sort_order') ?? 0;

        $setlist->items()->create([
            'sort_order' => $maxOrder + 1,
            'display_label' => $validated['display_label'] ?? null,
            'title' => $validated['title'],
        ]);

        return redirect()->route('setlists.show', $tour)
            ->with('success', '曲を追加しました');
    }

    public function destroyItem(Tour $tour, SetlistItem $item)
    {
        if ($item->setlist->tour_id !== $tour->id) {
            abort(404);
        }

        $item->delete();

        return redirect()->route('setlists.show', $tour)
            ->with('success', '曲を削除しました');
    }

    public function jsonImport(Request $request, Tour $tour)
    {
        $json = $request->input('json_text');

        if (! $json) {
            return back()->with('error', 'JSON文字列を入力してください');
        }

        $result = json_decode($json, true);

        if (! is_array($result) || ! isset($result['items'])) {
            return back()->with('error', 'JSONの形式が正しくありません。{"items":[...]} の形式にしてください');
        }

        $tour->load('setlists.items');
        $aiItems = $result['items'];

        return view('setlists.show', compact('tour', 'aiItems'));
    }

    public function aiParse(Request $request, Tour $tour)
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ]);

        try {
            $result = $this->llm->parseSetlist($validated['text']);
        } catch (\Throwable $e) {
            return back()->with('error', 'AI解析に失敗しました: ' . $e->getMessage());
        }

        $tour->load('setlists.items');
        $aiItems = $result['items'] ?? [];

        return view('setlists.show', compact('tour', 'aiItems'));
    }

    public function bulkStore(Request $request, Tour $tour)
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.display_label' => ['nullable', 'string', 'max:50'],
            'items.*.include' => ['nullable'],
        ]);

        $setlist = $tour->setlists()->create(['label' => $validated['label'] ?? null]);
        $count = 0;
        $order = 0;

        DB::transaction(function () use ($validated, $setlist, &$order, &$count) {
            foreach ($validated['items'] as $item) {
                if (empty($item['include'])) {
                    continue;
                }
                $setlist->items()->create([
                    'sort_order' => ++$order,
                    'display_label' => $item['display_label'] ?? null,
                    'title' => $item['title'],
                ]);
                $count++;
            }
        });

        return redirect()->route('setlists.show', $tour)
            ->with('success', "{$count}曲を登録しました");
    }
}
