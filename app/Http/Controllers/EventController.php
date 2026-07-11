<?php

namespace App\Http\Controllers;

use App\Contracts\LlmService;
use App\Models\Event;
use App\Models\Tour;
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
        private LlmService $llm,
    ) {
    }

    /** 公演一覧＝グループカード一覧（spec v2.5 §2.2・3階層の第1層） */
    public function index()
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        $myGroupIds = $user->idolGroups()->pluck('idol_groups.id');

        $groupsWithTours = \App\Models\IdolGroup::withCount(['tours' => fn ($q) => $q->has('events')])
            ->where(function ($q) use ($myGroupIds) {
                $q->whereIn('id', $myGroupIds)
                  ->orWhereHas('tours', fn ($tq) => $tq->has('events'));
            })
            ->orderBy('name')
            ->get();

        $hasUncategorized = Tour::whereNull('idol_group_id')->has('events')->exists();

        return view('events.index', ['groups' => $groupsWithTours, 'hasUncategorized' => $hasUncategorized]);
    }

    /** 第2階層: グループ内ツアー一覧 */
    public function groupTours(\App\Models\IdolGroup $idolGroup)
    {
        $today = Carbon::today();

        $tours = $idolGroup->tours()
            ->withCount('events')
            ->with(['events' => fn ($q) => $q->orderBy('event_date')])
            ->get()
            ->map(function ($tour) use ($today) {
                $hasUpcoming = $tour->events->contains(fn ($e) => $e->event_date->gte($today));
                $tour->status_label = $tour->events_count === 0 ? '日程未登録' : ($hasUpcoming ? '開催中' : '終了');
                return $tour;
            })
            ->sortByDesc(fn ($t) => optional($t->events->max('event_date'))->timestamp)
            ->values();

        return view('events.group-tours', compact('idolGroup', 'tours'));
    }

    /** 第2階層: 未分類ツアー一覧 */
    public function uncategorizedTours()
    {
        $today = Carbon::today();

        $tours = Tour::whereNull('idol_group_id')
            ->withCount('events')
            ->with(['events' => fn ($q) => $q->orderBy('event_date')])
            ->get()
            ->map(function ($tour) use ($today) {
                $hasUpcoming = $tour->events->contains(fn ($e) => $e->event_date->gte($today));
                $tour->status_label = $tour->events_count === 0 ? '日程未登録' : ($hasUpcoming ? '開催中' : '終了');
                return $tour;
            })
            ->sortByDesc(fn ($t) => optional($t->events->max('event_date'))->timestamp)
            ->values();

        return view('events.group-tours', ['idolGroup' => null, 'tours' => $tours]);
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

        $dups = $this->service->findDuplicates($venueId, $validated['event_date'], $startTime);
        if ($dups->isNotEmpty() && empty($validated['confirm_duplicate'])) {
            return back()
                ->withInput()
                ->with('duplicate_warning', '同じ会場・同じ日付・同じ開演の日程が既にあります（昼夜2公演なら開演時間を変えて続行）。');
        }

        $event = $this->service->create(
            $tour->id, $validated['event_label'] ?? null, $validated['event_date'], $startTime, $venueId
        );

        return redirect()->route('tours.show', $tour)
            ->with('success', '日程を登録しました');
    }

    public function edit(Event $event)
    {
        $event->load(['tour', 'venue']);

        return view('events.edit', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'event_label' => ['nullable', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
        ]);

        $venueId = $this->service->resolveVenueId($validated);

        $event->update([
            'event_label' => $validated['event_label'] ?? null,
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'venue_id' => $venueId,
        ]);

        return redirect()->route('tours.show', $event->tour)
            ->with('success', '日程を更新しました');
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

    public function importJson(Request $request)
    {
        $eventsGroups = [];
        $setlistGroups = [];
        $unknownFiles = [];
        $errors = [];

        $classify = function (array $decoded, string $source) use (&$eventsGroups, &$setlistGroups, &$unknownFiles) {
            if (isset($decoded['events']) && is_array($decoded['events'])) {
                $eventsGroups[] = [
                    'source' => $source,
                    'tour' => $decoded['tour'] ?? null,
                    'events' => $decoded['events'],
                    'deadlines' => $decoded['deadlines'] ?? [],
                ];
            } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
                $setlistGroups[] = [
                    'source' => $source,
                    'tour' => $decoded['tour'] ?? null,
                    'items' => $decoded['items'],
                ];
            } elseif (is_array($decoded) && isset($decoded[0])) {
                foreach ($decoded as $i => $entry) {
                    if (isset($entry['events']) && is_array($entry['events'])) {
                        $eventsGroups[] = [
                            'source' => $source . '[' . $i . ']',
                            'tour' => $entry['tour'] ?? null,
                            'events' => $entry['events'],
                            'deadlines' => $entry['deadlines'] ?? [],
                        ];
                    } elseif (isset($entry['items']) && is_array($entry['items'])) {
                        $setlistGroups[] = [
                            'source' => $source . '[' . $i . ']',
                            'tour' => $entry['tour'] ?? null,
                            'items' => $entry['items'],
                        ];
                    } else {
                        $unknownFiles[] = $source . '[' . $i . ']';
                    }
                }
            } else {
                $unknownFiles[] = $source;
            }
        };

        if ($request->hasFile('json_files')) {
            foreach ($request->file('json_files') as $file) {
                $content = $file->get();
                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    $errors[] = $file->getClientOriginalName() . ' はJSON形式として読み込めませんでした';
                    continue;
                }
                $classify($decoded, $file->getClientOriginalName());
            }
        }

        if ($request->filled('json_text')) {
            $decoded = json_decode($request->input('json_text'), true);
            if (! is_array($decoded)) {
                $errors[] = 'テキスト欄のJSONが正しい形式ではありません';
            } else {
                $classify($decoded, 'テキスト入力');
            }
        }

        if (empty($eventsGroups) && empty($setlistGroups) && empty($errors)) {
            return back()->with('error', 'JSONファイルまたはJSON文字列を入力してください');
        }

        if (empty($eventsGroups) && empty($setlistGroups) && ! empty($errors)) {
            return back()->with('error', implode('。', $errors));
        }

        $validationErrors = $this->validateJsonEntries($eventsGroups, $setlistGroups);

        return view('events.import-confirm-json', compact(
            'eventsGroups', 'setlistGroups', 'unknownFiles', 'errors', 'validationErrors'
        ));
    }

    private function validateJsonEntries(array &$eventsGroups, array &$setlistGroups): array
    {
        $errors = [];

        foreach ($eventsGroups as $gi => &$group) {
            foreach ($group['events'] as $ei => &$event) {
                if (! empty($event['event_date']) && ! strtotime($event['event_date'])) {
                    $errors[] = ($group['source'] ?? 'events') . " の{$ei}行目: event_date「{$event['event_date']}」は有効な日付ではありません";
                    $event['_invalid'] = true;
                }
                if (! empty($event['start_time']) && ! preg_match('/^\d{1,2}:\d{2}$/', $event['start_time'])) {
                    $errors[] = ($group['source'] ?? 'events') . " の{$ei}行目: start_time「{$event['start_time']}」はHH:MM形式ではありません";
                    $event['_invalid'] = true;
                }
            }
            unset($event);
        }
        unset($group);

        foreach ($setlistGroups as $gi => &$group) {
            foreach ($group['items'] as $ii => &$item) {
                if (empty($item['title'])) {
                    $errors[] = ($group['source'] ?? 'setlist') . " の{$ii}行目: title（曲名）が空です";
                    $item['_invalid'] = true;
                }
                if (isset($item['order']) && ! is_int($item['order'])) {
                    $errors[] = ($group['source'] ?? 'setlist') . " の{$ii}行目: order は整数である必要があります";
                }
            }
            unset($item);
        }
        unset($group);

        return $errors;
    }

    public function importJsonStore(Request $request)
    {
        $rules = [];
        $messages = [
            'events_groups.*.tour_name.required' => 'ツアー名を入力してください',
            'setlist_groups.*.tour_name.required' => 'セットリストのツアー名を入力してください',
        ];

        if ($request->has('events_groups')) {
            $rules['events_groups'] = ['nullable', 'array'];
            $rules['events_groups.*.tour_name'] = ['required', 'string', 'max:255'];
            $rules['events_groups.*.events'] = ['required', 'array'];
            $rules['events_groups.*.events.*.include'] = ['nullable'];
            $rules['events_groups.*.events.*.event_date'] = ['nullable', 'date'];
            $rules['events_groups.*.events.*.start_time'] = ['nullable', 'date_format:H:i'];
            $rules['events_groups.*.events.*.venue_name'] = ['nullable', 'string', 'max:255'];
            $rules['events_groups.*.events.*.event_label'] = ['nullable', 'string', 'max:255'];
            $rules['events_groups.*.deadlines'] = ['nullable', 'array'];
            $rules['events_groups.*.deadlines.*.label'] = ['nullable', 'string', 'max:255'];
            $rules['events_groups.*.deadlines.*.application_deadline'] = ['nullable', 'date'];
            $rules['events_groups.*.deadlines.*.announce_date'] = ['nullable', 'date'];
        }

        if ($request->has('setlist_groups')) {
            $rules['setlist_groups'] = ['nullable', 'array'];
            $rules['setlist_groups.*.tour_name'] = ['required', 'string', 'max:255'];
            $rules['setlist_groups.*.items'] = ['required', 'array', 'min:1'];
            $rules['setlist_groups.*.items.*.include'] = ['nullable'];
            $rules['setlist_groups.*.items.*.title'] = ['required', 'string', 'max:255'];
            $rules['setlist_groups.*.items.*.display_label'] = ['nullable', 'string', 'max:50'];
        }

        $validator = validator($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->route('events.import')
                ->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();
        $importedEvents = 0;
        $importedSetlists = 0;

        DB::transaction(function () use ($validated, &$importedEvents, &$importedSetlists) {
            foreach ($validated['events_groups'] ?? [] as $tourData) {
                $tourId = $this->service->resolveTourId(['tour_name' => $tourData['tour_name']]);

                foreach ($tourData['events'] as $row) {
                    if (empty($row['include']) || empty($row['event_date'])) {
                        continue;
                    }
                    $venueId = $this->service->resolveVenueId(['venue_name' => $row['venue_name'] ?? null]);
                    $this->service->create(
                        $tourId, $row['event_label'] ?? null, $row['event_date'],
                        ! empty($row['start_time']) ? $row['start_time'] : null, $venueId
                    );
                    $importedEvents++;
                }

                foreach ($tourData['deadlines'] ?? [] as $dl) {
                    if (empty($dl['application_deadline']) && empty($dl['announce_date'])) {
                        continue;
                    }
                    \App\Models\TourDeadline::create([
                        'tour_id' => $tourId,
                        'label' => $dl['label'] ?? null,
                        'application_deadline' => $dl['application_deadline'] ?? null,
                        'announce_date' => $dl['announce_date'] ?? null,
                    ]);
                }
            }

            foreach ($validated['setlist_groups'] ?? [] as $setlistData) {
                $tourId = $this->service->resolveTourId(['tour_name' => $setlistData['tour_name']]);
                $setlist = \App\Models\Setlist::create(['tour_id' => $tourId, 'label' => null]);
                $order = 0;

                foreach ($setlistData['items'] as $item) {
                    if (empty($item['include'])) {
                        continue;
                    }
                    $setlist->items()->create([
                        'sort_order' => ++$order,
                        'display_label' => $item['display_label'] ?? null,
                        'title' => $item['title'],
                    ]);
                    $importedSetlists++;
                }
            }
        });

        if ($importedEvents === 0 && $importedSetlists === 0) {
            return back()->with('error', '取込対象のデータがありませんでした');
        }

        $parts = [];
        if ($importedEvents > 0) {
            $parts[] = "{$importedEvents}件の公演";
        }
        if ($importedSetlists > 0) {
            $parts[] = "{$importedSetlists}曲のセットリスト";
        }

        return redirect()->route('events.index')
            ->with('success', implode('・', $parts) . 'を登録しました');
    }

    public function importParse(Request $request)
    {
        $request->validate([
            'text' => ['nullable', 'string'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,webp', 'max:10240'],
        ], [
            'images.max' => '画像は最大5枚までです',
            'images.*.image' => '画像ファイル(jpeg/png/webp)をアップロードしてください',
            'images.*.mimes' => '対応形式はjpeg・png・webpです',
            'images.*.max' => '1枚あたり10MB以内にしてください',
        ]);

        $text = $request->input('text');
        $imagePaths = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $imagePaths[] = $file->getRealPath();
            }
        }

        if (empty($text) && empty($imagePaths)) {
            return back()->withInput()->with('error', '画像またはテキストを入力してください');
        }

        try {
            $result = $this->llm->parseEvents($text, $imagePaths);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'AI解析に失敗しました: ' . $e->getMessage());
        }

        return view('events.import-confirm', [
            'rows' => $result['events'] ?? [],
            'unknown' => [],
            'tour' => $result['tour'] ?? '',
            'deadlines' => $result['deadlines'] ?? [],
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
            'deadlines' => ['nullable', 'array'],
            'deadlines.*.label' => ['nullable', 'string', 'max:255'],
            'deadlines.*.application_deadline' => ['nullable', 'date'],
            'deadlines.*.announce_date' => ['nullable', 'date'],
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
            $tourId = $this->service->resolveTourId(['tour_name' => $validated['tour_name']]);

            foreach ($validated['rows'] as $row) {
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

            foreach ($validated['deadlines'] ?? [] as $dl) {
                if (empty($dl['application_deadline']) && empty($dl['announce_date'])) {
                    continue;
                }
                \App\Models\TourDeadline::create([
                    'tour_id' => $tourId,
                    'label' => $dl['label'] ?? null,
                    'application_deadline' => $dl['application_deadline'] ?? null,
                    'announce_date' => $dl['announce_date'] ?? null,
                ]);
            }
        });

        if ($imported === 0) {
            return back()->with('error', '取込対象の行がありませんでした');
        }

        return redirect()->route('events.index')
            ->with('success', "{$imported}件の日程を共有マスタに登録しました");
    }
}
