<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Venue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function create(array $data, array $identityIds = []): Attendance
    {
        return DB::transaction(function () use ($data, $identityIds) {
            $venueId = $this->resolveVenueId($data);

            $attendance = Attendance::create([
                'user_id' => Auth::id(),
                'venue_id' => $venueId,
                'event_name' => $data['event_name'],
                'event_date' => $data['event_date'],
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
            $venueId = $this->resolveVenueId($data);

            $attendance->update([
                'venue_id' => $venueId,
                'event_name' => $data['event_name'],
                'event_date' => $data['event_date'],
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

    private function resolveVenueId(array $data): ?int
    {
        if (! empty($data['venue_id'])) {
            return (int) $data['venue_id'];
        }

        if (! empty($data['venue_name'])) {
            // 名前完全一致の既存会場があれば再利用（spec §5-10-3）。なければ新規作成
            $existing = Venue::where('name', $data['venue_name'])->first();
            if ($existing) {
                return $existing->id;
            }

            // 住所はユーザーが確認・確定した入力値のみ保存（規約0-7）
            $venue = Venue::create([
                'name' => $data['venue_name'],
                'address' => $data['venue_address'] ?? null,
                'created_by' => Auth::id(),
            ]);
            return $venue->id;
        }

        return null;
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
