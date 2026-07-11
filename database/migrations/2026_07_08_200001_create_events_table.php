<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 公演情報の共有マスタ（v1.2新設・spec §4）。
 * user_id を持たない全ユーザー共通マスタ（venues と同型・規約0-6の例外②）。
 * 追加・編集は全ユーザー可、削除は紐づく attendances 0件時のみ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name');
            $table->date('event_date');
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
