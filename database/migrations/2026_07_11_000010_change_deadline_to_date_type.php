<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_deadlines', function (Blueprint $table) {
            $table->date('application_deadline')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tour_deadlines', function (Blueprint $table) {
            $table->dateTime('application_deadline')->nullable()->change();
        });
    }
};
