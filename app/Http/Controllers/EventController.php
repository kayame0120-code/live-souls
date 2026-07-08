<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use App\Services\LotImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 公演共有マスタ（events）。全ユーザー読取／追加／編集可・user_idスコープなし（規約0-6の例外②）。
 * 削除は紐づく attendances 0件時のみ。
 */
class EventController extends Controller
{
    public function __construct(
        private EventService $service,
        private LotImportService $importService,
    ) {
    }

    public function index()
    {
        $events = Event::with('venue')
            ->orderByDesc('event_date')
            ->get();

        return view('events.index', compact('events'));
    }

    public function create()
    {
        return view('events.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ], [
            'event_name.required' => '公演名を入力してください',
            'event_date.required' => '日付を入力してください',
        ]);

        $venueId = $this->service->resolveVenueId($validated);

        // 同一会場×同一日付は警告（ブロックしない・昼夜2公演があるため）。
        // 未確認なら確認画面へ戻す。confirm_duplicate=1 で続行。
        $dups = $this->service->findDuplicates($venueId, $validated['event_date']);
        if ($dups->isNotEmpty() && empty($validated['confirm_duplicate'])) {
            return back()
                ->withInput()
                ->with('duplicate_warning', '同じ会場・同じ日付の公演が既にあります（昼夜2公演なら続行OK）。重複でなければ日付か公演名を確認してください。');
        }

        $this->service->create($validated['event_name'], $validated['event_date'], $venueId);

        return redirect()->route('events.index')
            ->with('success', '公演を共有マスタに登録しました');
    }

    public function destroy(Event $event)
    {
        // 紐づく参戦がある公演は削除不可（venues/グループ削除と同型・spec §5）
        if (! $event->canBeDeleted()) {
            return back()->with('error', 'この公演には参戦記録が紐づいているため削除できません');
        }

        $event->delete();

        return redirect()->route('events.index')
            ->with('success', '公演を削除しました');
    }

    /** 検索付きセレクト用の events サジェスト（全ユーザー・読取のみ・規約0-6④） */
    public function suggest(Request $request)
    {
        $q = $request->get('q', '');
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $events = Event::with('venue')
            ->where('event_name', 'like', "%{$q}%")
            ->orderByDesc('event_date')
            ->limit(15)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'event_name' => $e->event_name,
                'event_date' => $e->event_date->format('Y-m-d'),
                'venue_name' => $e->venue?->name,
            ]);

        return response()->json($events);
    }

    // ---- 一括インポート（/events/import・全ユーザー可・名義選択なし） ----

    public function importForm()
    {
        return view('events.import');
    }

    public function importParse(Request $request)
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ], [
            'text.required' => 'テキストを貼り付けてください',
        ]);

        $rows = $this->importService->parse($validated['text']);

        return view('events.import-confirm', compact('rows'));
    }

    public function importStore(Request $request)
    {
        $validator = validator($request->all(), [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.include' => ['nullable', 'boolean'],
            'rows.*.event_name' => ['nullable', 'string', 'max:255'],
            'rows.*.event_date' => ['nullable', 'date'],
            'rows.*.venue_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return redirect()->route('events.import')
                ->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();
        $imported = 0;

        DB::transaction(function () use ($validated, &$imported) {
            foreach ($validated['rows'] as $row) {
                // 必須未充足（event_name/event_date）・除外行は取込対象外
                if (empty($row['include']) || empty($row['event_name']) || empty($row['event_date'])) {
                    continue;
                }

                $venueId = $this->service->resolveVenueId([
                    'venue_name' => $row['venue_name'] ?? null,
                ]);

                $this->service->create($row['event_name'], $row['event_date'], $venueId);
                $imported++;
            }
        });

        if ($imported === 0) {
            return back()->with('error', '取込対象の行がありませんでした');
        }

        return redirect()->route('events.index')
            ->with('success', "{$imported}件の公演を共有マスタに登録しました");
    }
}
