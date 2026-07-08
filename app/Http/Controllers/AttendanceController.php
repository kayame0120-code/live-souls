<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceIdentity;
use App\Models\FcMembership;
use App\Models\Scopes\UserScope;
use App\Services\AttendanceService;
use App\Services\PhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $service,
        private PhotoService $photoService,
    ) {
    }

    public function index(Request $request)
    {
        $year = $request->get('year');

        // タイムラインは applied を表示しない（spec §5-7-4）
        $query = Attendance::with(['venue', 'fcMemberships.person'])
            ->where('status', '!=', 'applied')
            ->orderByDesc('event_date');

        if ($year && $year !== 'all') {
            $query->whereYear('event_date', $year);
        }

        $attendances = $query->get();

        $years = Attendance::where('status', '!=', 'applied')
            ->pluck('event_date')
            ->map(fn ($d) => $d->format('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();

        if (empty($years)) {
            $years = [(string) now()->year];
        }

        return view('attendances.index', compact('attendances', 'years', 'year'));
    }

    public function create()
    {
        $memberships = FcMembership::with('person')->get();
        return view('attendances.create', compact('memberships'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);

        $identityIds = $validated['identity_ids'] ?? [];
        $attendance = $this->service->create($validated, $identityIds);

        $this->storeUploadedPhotos($request, $attendance);

        return redirect()->route('attendances.index')
            ->with('success', '参戦記録を保存しました');
    }

    public function show(Attendance $attendance)
    {
        $attendance->load(['venue', 'fcMemberships.person', 'photos.user']);
        return view('attendances.show', compact('attendance'));
    }

    public function edit(Attendance $attendance)
    {
        $attendance->load(['fcMemberships', 'photos']);
        $memberships = FcMembership::with('person')->get();
        return view('attendances.edit', compact('attendance', 'memberships'));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $this->validatedData($request, $attendance);

        $identityIds = $validated['identity_ids'] ?? [];
        $this->service->update($attendance, $validated, $identityIds);

        $this->storeUploadedPhotos($request, $attendance);

        return redirect()->route('attendances.show', $attendance)
            ->with('success', '参戦記録を更新しました');
    }

    public function destroy(Attendance $attendance)
    {
        // 添付写真のファイルも削除する
        foreach ($attendance->photos as $photo) {
            $this->photoService->delete($photo);
        }

        $attendance->delete();
        return redirect()->route('attendances.index')
            ->with('success', '参戦記録を削除しました');
    }

    /**
     * 当落結果の更新（S5/S9）。
     * AttendanceIdentity は UserScope 対象外のため、attendance 経由で所有者を明示検証する。
     * result=won が1件でも付いたら applied → planned に自動昇格（spec §5-7-3。降格はしない）。
     */
    public function updateResult(Request $request, int $pivotId)
    {
        $validated = $request->validate([
            'result' => ['required', 'in:pending,won,lost'],
        ]);

        $pivot = AttendanceIdentity::where('id', $pivotId)
            ->whereHas('attendance', fn ($q) => $q->where('user_id', Auth::id()))
            ->firstOrFail();

        $pivot->update(['result' => $validated['result']]);

        $attendance = Attendance::withoutGlobalScope(UserScope::class)
            ->findOrFail($pivot->attendance_id);

        // 当選昇格: applied のときのみ planned へ。won が0件に戻っても自動降格しない
        if ($attendance->status === 'applied'
            && $attendance->fcMemberships()->wherePivot('result', 'won')->exists()) {
            $attendance->update(['status' => 'planned']);
        }

        return redirect(url()->previous() ?: route('lots.index'))
            ->with('success', '当落結果を更新しました');
    }

    /** 登録・更新共通のバリデーション */
    private function validatedData(Request $request, ?Attendance $attendance = null): array
    {
        $existingPhotoCount = $attendance ? $attendance->photos()->count() : 0;
        $maxNewPhotos = max(0, PhotoService::MAX_PHOTOS_PER_ATTENDANCE - $existingPhotoCount);

        return $request->validate([
            'event_name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'seat_raw' => ['nullable', 'string', 'max:255'],
            'seat_block' => ['nullable', 'string', 'max:50'],
            'seat_row' => ['nullable', 'string', 'max:50'],
            'seat_number' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:applied,planned,attended,skipped'],
            'companion' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string'],
            'identity_ids' => ['nullable', 'array'],
            'identity_ids.*' => [Rule::exists('fc_memberships', 'id')->where('user_id', Auth::id())],
            // 写真: 1参戦5枚まで・1枚10MBまで・jpeg/png/webp（heicはQUESTIONS.md Q5参照）
            'photos' => ['nullable', 'array', "max:{$maxNewPhotos}"],
            'photos.*' => ['file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ], [
            // spec §6 指定のエラーメッセージ
            'event_name.required' => '公演名を入力してください',
            'event_date.required' => '日付を入力してください',
            'photos.max' => "写真は1参戦につき" . PhotoService::MAX_PHOTOS_PER_ATTENDANCE . "枚までです",
        ]);
    }

    private function storeUploadedPhotos(Request $request, Attendance $attendance): void
    {
        foreach ($request->file('photos', []) as $file) {
            $this->photoService->store($attendance, $file);
        }
    }
}
