<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.4 破壊的移行①（spec §8手順2-3・指示書v1.4 §2-2）。
 * events.tour_id を追加し、既存 events から tours を逆生成する。
 * ルール：同じ event_name 文字列を持つ event 群は同一 tour に集約する
 *        （異なる文字列は別 tour＝安全側。意味的統合・分割の推測は絶対にしない）。
 * 生SQL不使用・クエリビルダ＋PHP。移行後にマイグレーション内で全件検証（tour_id 非NULL）。
 * NOT NULL 化と event_name DROP は後続マイグレーションで（検証を挟むため分離）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('tour_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // 既存 event_name の distinct ごとに tour を1件作成し、同名 event 群へ tour_id を埋める
        $names = DB::table('events')->select('event_name')->distinct()->pluck('event_name');

        foreach ($names as $name) {
            $tourId = DB::table('tours')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('events')->where('event_name', $name)->update(['tour_id' => $tourId]);
        }

        // 機械検証：全 events に tour_id が非NULLで埋まっていること
        $nullCount = DB::table('events')->whereNull('tour_id')->count();
        if ($nullCount > 0) {
            throw new \RuntimeException(
                "tour逆生成の検証失敗: tour_id未設定の events が {$nullCount}件あります"
            );
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tour_id');
        });
    }
};
