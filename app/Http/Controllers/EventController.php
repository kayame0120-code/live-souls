<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tour;
use App\Services\EventImportParser;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 公演（日程）の共有マスタ（events）と公演一覧（ツアーカード）。
 * 全ユーザー読取／追加／編集可・user_idスコープなし（規約0-6の例外②）。
 * v1.4: events は必ずツアー（tours）配下。一覧はツアーカードで表示する。
 */
class EventController extends Controller
{
    public function __construct(
        private EventService $service,
        private EventImportParser $parser,
    ) {
    }

    /** 公演一覧＝ツアーカード一覧（spec §3・mockup #scr-event） */
    public function index()
    {
        $today = Carbon::today();

        $tours = Tour::withCount('events')
            ->with(['events' => fn ($q) => $q->orderBy('event_date')])
            ->get()
            ->map(function ($tour) use ($today) {
                // 開催状況: 未来の日程が残っていれば「開催中」、無ければ「終了」
                $hasUpcoming = $tour->events->contains(fn ($e) => $e->event_date->gte($today));
                $tour->status_label = $tour->events_count === 0 ? '日程未登録' : ($hasUpcoming ? '開催中' : '終了');
                return $tour;
            })
            ->sortByDesc(fn ($t) => optional($t->events->max('event_date'))->timestamp)
            ->values();

        return view('events.index', compact('tours'));
    }

    /** 日程（event）登録フォーム（ツアー配下・旧 /events/create を置換） */
    public function create(Tour $tour)
    {
        return view('events.create', compact('tour'));
    }

    /** 日程（event）を対象ツアー配下に登録 */
    public function store(Request $request, Tour $tour)
    {
        $validated = $request->validate([
            'event_label' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ], [
            'event_date.required' => '日付を入力してください',
            'start_time.date_format' => '開演時間は HH:MM 形式で入力してください',
        ]);

        $venueId = $this->service->resolveVenueId($validated);
        $startTime = $validated['start_time'] ?? null;

        // 重複判定は 会場×日×開演（ツアーをまたいでも対象・spec §5）。ブロックはしない
        $dups = $this->service->findDuplicates($venueId, $validated['event_date'], $startTime);
        if ($dups->isNotEmpty() && empty($validated['confirm_duplicate'])) {
            return back()
                ->withInput()
                ->with('duplicate_warning', '同じ会場・同じ日付・同じ開演の日程が既にあります（昼夜2公演なら開演時間を変えて続行）。');
        }

        $this->service->create($tour->id, $validated['event_label'] ?? null, $validated['event_date'], $startTime, $venueId);

        return redirect()->route('tours.show', $tour)
            ->with('success', '日程を登録しました');
    }

    public function destroy(Event $event)
    {
        // 紐づく参戦がある日程は削除不可（venues/グループ削除と同型・spec §5）
        if (! $event->canBeDeleted()) {
            return back()->with('error', 'この日程には参戦記録が紐づいているため削除できません');
        }

        $tour = $event->tour;
        $event->delete();

        return redirect()->route('tours.show', $tour)
            ->with('success', '日程を削除しました');
    }

    /**
     * カスケード選択②用: 指定ツアー配下の日程を返す（v1.5・全ユーザー読取のみ）。
     * ラベルは「日付（曜）会場 開演（event_label）」で組み立てる。
     */
    public function eventsByTour(Tour $tour)
    {
        $events = $tour->events()
            ->with('venue')
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($e) {
                $parts = [$e->event_date->format('Y.m.d') . '（' . $e->event_date->translatedFormat('D') . '）'];
                if ($e->venue) {
                    $parts[] = $e->venue->name;
                }
                if ($e->start_time) {
                    $parts[] = $e->start_time->format('H:i');
                }
                $label = implode(' ', $parts);
                if ($e->event_label) {
                    $label .= '（' . $e->event_label . '）';
                }
                return ['id' => $e->id, 'label' => $label];
            });

        return response()->json($events);
    }

    /** ツアー名の検索付きセレクト用サジェスト（一括インポートのツアー解決・全ユーザー読取のみ） */
    public function toursSuggest(Request $request)
    {
        $q = $request->get('q', '');
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        return response()->json(
            Tour::where('name', 'like', "%{$q}%")->orderBy('name')->limit(15)->get(['id', 'name'])
        );
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

        // parse() は不変（jsx 1:1移植）。tour は全行共通、未解析行は捨てず確認画面に出す
        $result = $this->parser->extractEvents($validated['text']);

        return view('events.import-confirm', [
            'rows' => $result['events'],
            'unknown' => $result['unknown'],
            'tour' => $result['tour'],
        ]);
    }

    /**
     * 確認テーブルの内容で一括登録（v1.4: ツアー名を解決してから events を INSERT）。
     * ツアー名は1回の貼り付けで1つ（全行共通）。既存tour照合／無ければ新規作成。
     */
    public function importStore(Request $request)
    {
        $validator = validator($request->all(), [
            'tour_name' => ['required', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.include' => ['nullable', 'boolean'],
            'rows.*.event_label' => ['nullable', 'string', 'max:255'],
            'rows.*.event_date' => ['nullable', 'date'],
            'rows.*.start_time' => ['nullable', 'date_format:H:i'],
            'rows.*.venue_name' => ['nullable', 'string', 'max:255'],
        ], [
            'tour_name.required' => 'ツアー名を入力してください',
        ]);

        if ($validator->fails()) {
            return redirect()->route('events.import')
                ->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();
        $imported = 0;

        DB::transaction(function () use ($validated, &$imported) {
            // ツアー名解決（既存照合／無ければ新規作成・venue名寄せと同型）
            $tourId = $this->service->resolveTourId(['tour_name' => $validated['tour_name']]);

            foreach ($validated['rows'] as $row) {
                // 必須未充足（event_date）・除外行は取込対象外
                if (empty($row['include']) || empty($row['event_date'])) {
                    continue;
                }

                $venueId = $this->service->resolveVenueId([
                    'venue_name' => $row['venue_name'] ?? null,
                ]);

                $startTime = ! empty($row['start_time']) ? $row['start_time'] : null;
                $eventLabel = ! empty($row['event_label']) ? $row['event_label'] : null;

                $this->service->create($tourId, $eventLabel, $row['event_date'], $startTime, $venueId);
                $imported++;
            }
        });

        if ($imported === 0) {
            return back()->with('error', '取込対象の行がありませんでした');
        }

        return redirect()->route('events.index')
            ->with('success', "{$imported}件の日程を共有マスタに登録しました");
    }
}
