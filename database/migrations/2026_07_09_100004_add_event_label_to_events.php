<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.4 破壊的移行③-a（spec §8手順5・指示書v1.4 §2-4）。
 * event_label（日程差分ラベル・nullable）を追加。値は全て NULL で初期化。
 * 旧 event_name の全文は既に tours.name 側に転写済みのため、差分抽出はしない（安全側）。
 * 旧 event_name の DROP は後続マイグレーションで（検証を挟むため分離・renameColumn 禁止）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_label')->nullable()->after('tour_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_label');
        });
    }
};
