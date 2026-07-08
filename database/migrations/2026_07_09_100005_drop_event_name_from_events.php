<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.4 破壊的移行③-b（spec §8手順5・指示書v1.4 §2-4）。
 * 逆生成検証の通過後、旧 events.event_name を DROP する。
 * 以後、公演見出しは tours.name（＋event_label）経由で解決する。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 安全弁：event_name の値がすべて tours.name 側に存在することを確認してから DROP
        $orphan = DB::table('events')
            ->leftJoin('tours', 'events.tour_id', '=', 'tours.id')
            ->whereColumn('events.event_name', '!=', 'tours.name')
            ->count();
        if ($orphan > 0) {
            throw new \RuntimeException("event_name と tours.name の不一致が {$orphan}件。DROPを中止しました。");
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_name');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_name')->nullable();
        });
    }
};
