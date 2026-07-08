<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.4 破壊的移行②（spec §8手順4・指示書v1.4 §2-3）。
 * 逆生成と全件検証が通過した後、events.tour_id を NOT NULL 化する。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 安全弁：万一 tour_id 未設定が残っていれば停止（NOT NULL化前の最終確認）
        if (DB::table('events')->whereNull('tour_id')->exists()) {
            throw new \RuntimeException('tour_id 未設定の events が残っています。NOT NULL 化を中止しました。');
        }

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('tour_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('tour_id')->nullable()->change();
        });
    }
};
