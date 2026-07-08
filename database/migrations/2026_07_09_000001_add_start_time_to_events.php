<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A: events に start_time を追加（cc_instructions v1.3 §1-1・[確定]）。
 * 公演の同定粒度を「会場×日」→「会場×日×開演」に拡張し、同日昼夜を別公演レコードとして扱う。
 * 既存 events は start_time=NULL のまま（従来の粒度を保持・データ変換はしない）。
 * 参戦（attendances）は event_id で events を指すため、attendances 側のスキーマ変更は不要。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('event_date');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });
    }
};
