<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.2 破壊的移行②（spec §8・指示書T2-5）。
 * event_id への移行と機械検証が通過した後、旧 event_name / event_date / venue_id を DROP。
 * 以後、公演名・日付・会場は events 経由で解決する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['event_name', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('event_name')->nullable();
            $table->date('event_date')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
