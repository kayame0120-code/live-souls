<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventImportParser;
use App\Services\EventService;
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
        private EventImportParser $parser,
    ) {
    }

    public function index()
    {
        // 「参戦 N」は共有マスタとして全メンバー分を数える（削除ガードと同じ withoutGlobalScopes）
        $events = Event::with('venue')
            ->withCount(['attendances' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderBy('event_date')
            ->get();

        $today = \Illuminate\Support\Carbon::today();

        // 今後（本日以降）は近い順、過去は新しい順（mockup: 今後の公演 → 過去の公演）
        $upcoming = $events->filter(fn ($e) => $e->event_date->gte($today))->values();
        $past = $events->filter(fn ($e) => $e->event_date->lt($today))->sortByDesc('event_date')->values();

        return view('events.index', compact('upcoming', 'past'));
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
            'start_time' => ['nullable', 'date_format:H:i'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ], [
            'event_name.required' => '公演名を入力してください',
            'event_date.required' => '日付を入力してください',
            'start_time.date_format' => '開演時間は HH:MM 形式で入力してください',
        ]);

        $venueId = $this->service->resolveVenueId($validated);
        $startTime = $validated['start_time'] ?? null;

        // ★v1.3：重複判定は 会場×日×開演。start_time が違えば昼夜として通す（ブロックしない）。
        // 未確認なら確認画面へ戻す。confirm_duplicate=1 で続行。
        $dups = $this->service->findDuplicates($venueId, $validated['event_date'], $startTime);
        if ($dups->isNotEmpty() && empty($validated['confirm_duplicate'])) {
            return back()
                ->withInput()
                ->with('duplicate_warning', '同じ会場・同じ日付・同じ開演の公演が既にあります（昼夜2公演なら開演時間を変えて続行）。重複でなければ日付か公演名を確認してください。');
        }

        $this->service->create($validated['event_name'], $validated['event_date'], $startTime, $venueId);

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

        // ★v1.3：EventImportParser（jsx parse() の移植）で show 行を抽出。
        // ツアー名は全行へ適用、未解析行は捨てず確認画面に出す。
        $result = $this->parser->extractEvents($validated['text']);

        return view('events.import-confirm', [
            'rows' => $result['events'],
            'unknown' => $result['unknown'],
            'tour' => $result['tour'],
        ]);
    }

    public function importStore(Request $request)
    {
        $validator = validator($request->all(), [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.include' => ['nullable', 'boolean'],
            'rows.*.event_name' => ['nullable', 'string', 'max:255'],
            'rows.*.event_date' => ['nullable', 'date'],
            'rows.*.start_time' => ['nullable', 'date_format:H:i'],
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

                // start_time は空なら NULL。昼夜は別 start_time の別行として個別に登録される。
                $startTime = ! empty($row['start_time']) ? $row['start_time'] : null;

                $this->service->create($row['event_name'], $row['event_date'], $startTime, $venueId);
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
