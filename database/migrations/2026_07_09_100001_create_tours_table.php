<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ツアーの共有マスタ（v1.4新設・spec §4・指示書v1.4 §2-1）。
 * user_id を持たない全ユーザー共通マスタ（venues/events と同型・マルチテナント例外）。
 * 単発公演も「1公演だけのツアー」として1件作る（events.tour_id は必須になる）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
