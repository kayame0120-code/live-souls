<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.0→v1.1 破壊的DROP（spec §10-1 / 検証通過後に実行）。
 * ローカル実データは 2026-07-08 検証: club_name=全null / renewal_cycle=全null /
 * joined_month は genba:verify-joined-on で変換一致を機械確認済み。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->dropColumn(['club_name', 'joined_month', 'renewal_cycle']);
        });
    }

    public function down(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->string('club_name')->nullable();
            $table->string('joined_month')->nullable();
            $table->string('renewal_cycle')->nullable();
        });
    }
};
