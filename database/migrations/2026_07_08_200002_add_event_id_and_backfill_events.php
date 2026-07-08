<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.2 破壊的移行①（spec §8・指示書T2）。
 * attendances.event_id を追加し、既存の (event_name, event_date, venue_id) から
 * events を逆生成（同一3つ組は1件に集約）して event_id を埋める。
 * 生SQL不使用・クエリビルダ＋PHP。移行後にマイグレーション内で機械検証（不一致で停止）。
 * 旧3カラムの DROP は次のマイグレーションで（検証を挟むため分離）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // 既存参戦を (event_name, event_date, venue_id) でグループ化し events を逆生成
        $rows = DB::table('attendances')
            ->get(['id', 'event_name', 'event_date', 'venue_id']);

        $eventIdByKey = [];

        foreach ($rows as $row) {
            $dateKey = substr((string) $row->event_date, 0, 10); // 'Y-m-d'
            $venueKey = $row->venue_id === null ? 'null' : (string) $row->venue_id;
            $key = $row->event_name . '|' . $dateKey . '|' . $venueKey;

            if (! isset($eventIdByKey[$key])) {
                $eventIdByKey[$key] = DB::table('events')->insertGetId([
                    'event_name' => $row->event_name,
                    'event_date' => $dateKey,
                    'venue_id' => $row->venue_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('attendances')
                ->where('id', $row->id)
                ->update(['event_id' => $eventIdByKey[$key]]);
        }

        // 機械検証: 全参戦が event_id を持ち、event 経由の3つ組が元と一致すること
        $mismatch = DB::table('attendances')
            ->join('events', 'attendances.event_id', '=', 'events.id')
            ->get([
                'attendances.id',
                'attendances.event_name as a_name',
                'attendances.event_date as a_date',
                'attendances.venue_id as a_venue',
                'events.event_name as e_name',
                'events.event_date as e_date',
                'events.venue_id as e_venue',
            ])
            ->filter(function ($r) {
                return $r->a_name !== $r->e_name
                    || substr((string) $r->a_date, 0, 10) !== substr((string) $r->e_date, 0, 10)
                    || (int) $r->a_venue !== (int) $r->e_venue;
            });

        $nullEventId = DB::table('attendances')->whereNull('event_id')->count();

        if ($mismatch->isNotEmpty() || $nullEventId > 0) {
            throw new \RuntimeException(
                "event_id 移行の検証失敗: 値不一致={$mismatch->count()}件, event_id未設定={$nullEventId}件"
            );
        }
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
