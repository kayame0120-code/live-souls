<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * 公演共有マスタ（events）の作成補助（spec §5・指示書T5）。
 * events は user_id を持たない全ユーザー共通マスタ。
 */
class EventService
{
    /**
     * 会場を解決する。venue_id 指定があれば優先、なければ名前で既存検索、
     * それも無ければ新規作成（住所はユーザー確定値のみ・規約0-7）。
     */
    public function resolveVenueId(array $data): ?int
    {
        if (! empty($data['venue_id'])) {
            return (int) $data['venue_id'];
        }

        if (! empty($data['venue_name'])) {
            $existing = Venue::where('name', $data['venue_name'])->first();
            if ($existing) {
                return $existing->id;
            }

            $venue = Venue::create([
                'name' => $data['venue_name'],
                'address' => $data['venue_address'] ?? null,
                'created_by' => Auth::id(),
            ]);

            return $venue->id;
        }

        return null;
    }

    public function create(string $eventName, string $eventDate, ?string $startTime, ?int $venueId): Event
    {
        return Event::create([
            'event_name' => $eventName,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'venue_id' => $venueId,
        ]);
    }

    /**
     * 重複警告用の既存公演（ブロックはしない・spec §5「公演の共有マスタ管理」）。
     * ★v1.3：判定キーは venue_id × event_date × start_time。
     * start_time が違えば昼夜2公演として別物＝重複扱いにしない。
     * start_time が両方 NULL で venue_id×event_date が一致する場合のみ「同一公演の重複では？」で拾う。
     */
    public function findDuplicates(?int $venueId, string $eventDate, ?string $startTime)
    {
        if ($venueId === null) {
            return collect();
        }

        $query = Event::where('venue_id', $venueId)
            ->whereDate('event_date', $eventDate);

        if ($startTime === null || $startTime === '') {
            // 開演未設定同士のみ突き合わせる（時刻ありは別公演として通す）
            $query->whereNull('start_time');
        } else {
            // start_time は H:i:s で保存（Event::startTime アクセサ・QV13-5）。突合値も H:i:s へ正規化。
            $query->where('start_time', Carbon::parse($startTime)->format('H:i:s'));
        }

        return $query->get();
    }
}
