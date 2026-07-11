<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setlists', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropUnique(['event_id']);
            $table->dropColumn('event_id');
        });

        Schema::table('setlists', function (Blueprint $table) {
            $table->foreignId('tour_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable()->after('tour_id');
        });
    }

    public function down(): void
    {
        Schema::table('setlists', function (Blueprint $table) {
            $table->dropForeign(['tour_id']);
            $table->dropColumn(['tour_id', 'label']);
        });

        Schema::table('setlists', function (Blueprint $table) {
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
        });
    }
};
