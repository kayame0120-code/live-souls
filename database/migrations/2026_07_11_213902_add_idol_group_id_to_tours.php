<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('idol_group_id')->nullable()->after('name')
                ->constrained('idol_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['idol_group_id']);
            $table->dropColumn('idol_group_id');
        });
    }
};
