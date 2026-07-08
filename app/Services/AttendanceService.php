<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * 参戦を作成（v1.2: event_id で公演を参照。日付・会場・公演名は events 側）。
     * 手入力は座席3フィールドと写真のみ。名義選択分だけ pivot を生成する。
     */
    public function create(array $data, array $identityIds = []): Attendance
    {
        return DB::transaction(function () use ($data, $identityIds) {
            $attendance = Attendance::create([
                'user_id' => Auth::id(),
                'event_id' => $data['event_id'],
                'open_time' => $data['open_time'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'seat_raw' => $this->resolveSeatRaw($data),
                'seat_block' => $data['seat_block'] ?? null,
                'seat_row' => $data['seat_row'] ?? null,
                'seat_number' => $data['seat_number'] ?? null,
                'status' => $data['status'] ?? 'attended',
                'companion' => $data['companion'] ?? null,
                'memo' => $data['memo'] ?? null,
            ]);

            foreach ($identityIds as $id) {
                $attendance->fcMemberships()->attach($id, ['result' => 'pending']);
            }

            return $attendance;
        });
    }

    public function update(Attendance $attendance, array $data, array $identityIds = []): Attendance
    {
        return DB::transaction(function () use ($attendance, $data, $identityIds) {
            $attendance->update([
                'event_id' => $data['event_id'],
                'open_time' => $data['open_time'] ?? null,
                'start_time' => $data['start_time'] ?? null,
                'seat_raw' => $this->resolveSeatRaw($data),
                'seat_block' => $data['seat_block'] ?? null,
                'seat_row' => $data['seat_row'] ?? null,
                'seat_number' => $data['seat_number'] ?? null,
                'status' => $data['status'] ?? 'attended',
                'companion' => $data['companion'] ?? null,
                'memo' => $data['memo'] ?? null,
            ]);

            $existingPivots = $attendance->fcMemberships()->pluck('fc_membership_id')->toArray();
            $toDetach = array_diff($existingPivots, $identityIds);
            $toAttach = array_diff($identityIds, $existingPivots);

            if ($toDetach) {
                $attendance->fcMemberships()->detach($toDetach);
            }
            foreach ($toAttach as $id) {
                $attendance->fcMemberships()->attach($id, ['result' => 'pending']);
            }

            return $attendance->fresh();
        });
    }

    /**
     * seat_raw の決定（spec §5-8）:
     * 送信された seat_raw（手動編集値）を優先。空なら構造化3フィールドから自動合成。
     */
    private function resolveSeatRaw(array $data): ?string
    {
        $manual = $data['seat_raw'] ?? null;
        if ($manual !== null && $manual !== '') {
            return $manual;
        }

        return Attendance::composeSeatRaw(
            $data['seat_block'] ?? null,
            $data['seat_row'] ?? null,
            $data['seat_number'] ?? null,
        );
    }
}
