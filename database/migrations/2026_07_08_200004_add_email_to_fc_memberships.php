<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * fc_memberships に email（FC登録メールアドレス）を追加（spec §4・指示書T3）。
 * encrypted 値を格納するため text 型。login_id とは別に維持する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->text('email')->nullable()->after('login_id');
        });
    }

    public function down(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
