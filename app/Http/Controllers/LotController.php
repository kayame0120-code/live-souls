<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\FcMembership;
use App\Services\AttendanceService;
use App\Services\LotImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LotController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private LotImportService $importService,
    ) {
    }

    public function index()
    {
        $attendances = Attendance::with(['venue', 'fcMemberships.person'])
            ->whereHas('fcMemberships')
            ->orderByDesc('event_date')
            ->get();

        $pending = $attendances->filter(function ($a) {
            return $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending');
        });

        $decided = $attendances->filter(function ($a) {
            return ! $a->fcMemberships->contains(fn ($m) => $m->pivot->result === 'pending');
        });

        return view('lots.index', compact('pending', 'decided'));
    }

    /** 申込登録フォーム（S9・spec §5-7-1） */
    public function create()
    {
        $memberships = FcMembership::with('person')->get();
        return view('lots.create', compact('memberships'));
    }

    /** 申込登録: attendances(status=applied) + pivot(result=pending)。名義は1つ以上必須 */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'identity_ids' => ['required', 'array', 'min:1'],
            'identity_ids.*' => [Rule::exists('fc_memberships', 'id')->where('user_id', Auth::id())],
        ], [
            'event_name.required' => '公演名を入力してください',
            'event_date.required' => '日付を入力してください',
            'identity_ids.required' => '申込名義を選択してください',
            'identity_ids.min' => '申込名義を選択してください',
        ]);

        $validated['status'] = 'applied';
        $this->attendanceService->create($validated, $validated['identity_ids']);

        return redirect()->route('lots.index')
            ->with('success', '申込を登録しました');
    }

    /** 一括インポート: 貼り付けフォーム（S11・spec §5-10） */
    public function importForm()
    {
        return view('lots.import');
    }

    /** 貼り付けテキストを解析し確認テーブルを表示 */
    public function importParse(Request $request)
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
        ], [
            'text.required' => 'テキストを貼り付けてください',
        ]);

        $rows = $this->importService->parse($validated['text']);
        $memberships = FcMembership::with('person')->get();

        return view('lots.import-confirm', compact('rows', 'memberships'));
    }

    /** 確認テーブルの内容で一括登録（各行 status=applied + pivot pending） */
    public function importStore(Request $request)
    {
        // 確認画面はPOSTレンダリングのため back() できない。失敗時は貼り付け画面へ戻す
        $validator = validator($request->all(), [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.include' => ['nullable', 'boolean'],
            'rows.*.event_name' => ['nullable', 'string', 'max:255'],
            'rows.*.event_date' => ['nullable', 'date'],
            'rows.*.venue_name' => ['nullable', 'string', 'max:255'],
            'identity_ids' => ['required', 'array', 'min:1'],
            'identity_ids.*' => [Rule::exists('fc_memberships', 'id')->where('user_id', Auth::id())],
        ], [
            'identity_ids.required' => '申込名義を選択してください',
            'identity_ids.min' => '申込名義を選択してください',
        ]);

        if ($validator->fails()) {
            return redirect()->route('lots.import')
                ->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();

        $imported = 0;
        DB::transaction(function () use ($validated, &$imported) {
            foreach ($validated['rows'] as $row) {
                // 除外行・必須未充足行（event_name/event_date）は取込対象外（spec §6）
                if (empty($row['include']) || empty($row['event_name']) || empty($row['event_date'])) {
                    continue;
                }

                $this->attendanceService->create([
                    'event_name' => $row['event_name'],
                    'event_date' => $row['event_date'],
                    'venue_name' => $row['venue_name'] ?? null,
                    'status' => 'applied',
                ], $validated['identity_ids']);

                $imported++;
            }
        });

        if ($imported === 0) {
            return back()->with('error', '取込対象の行がありませんでした');
        }

        return redirect()->route('lots.index')
            ->with('success', "{$imported}件の申込を登録しました");
    }
}
