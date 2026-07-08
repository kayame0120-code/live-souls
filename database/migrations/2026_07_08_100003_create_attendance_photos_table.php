<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 参戦写真（v1.1新設・spec §4）。
 * user_id は投稿者。閲覧はメンバー間共有（規約0-6の例外②）のため
 * グローバルuser_idスコープは適用しない。書込・削除は投稿者本人のみ（Policy）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_photos');
    }
};
