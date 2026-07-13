<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            // 会員番号の下3桁ヒント（一覧表示用・平文）。
            // E2E暗号文はサーバーで復号できないため、保存時にクライアントが下3桁のみを別送する
            // （カード下4桁表示と同じパターン。下3桁単体はアカウント乗っ取りに使えない低機微情報）
            $table->string('member_no_hint', 3)->nullable()->after('member_no');
        });
    }

    public function down(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->dropColumn('member_no_hint');
        });
    }
};
