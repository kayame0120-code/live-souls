<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Venue;
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

    public function create(string $eventName, string $eventDate, ?int $venueId): Event
    {
        return Event::create([
            'event_name' => $eventName,
            'event_date' => $eventDate,
            'venue_id' => $venueId,
        ]);
    }

    /** 同一会場×同一日付の既存公演（重複警告用・ブロックはしない） */
    public function findDuplicates(?int $venueId, string $eventDate)
    {
        if ($venueId === null) {
            return collect();
        }

        return Event::where('venue_id', $venueId)
            ->whereDate('event_date', $eventDate)
            ->get();
    }
}
