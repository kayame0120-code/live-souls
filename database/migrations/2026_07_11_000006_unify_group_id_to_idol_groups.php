<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
        });

        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->change();
            $table->foreign('group_id')->references('id')->on('idol_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
        });

        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->foreign('group_id')->references('id')->on('identity_groups')->nullOnDelete();
        });
    }
};
