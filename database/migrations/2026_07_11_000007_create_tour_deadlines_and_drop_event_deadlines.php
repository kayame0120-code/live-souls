<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->dateTime('application_deadline')->nullable();
            $table->date('announce_date')->nullable();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['application_deadline', 'announce_date']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('application_deadline')->nullable();
            $table->date('announce_date')->nullable();
        });

        Schema::dropIfExists('tour_deadlines');
    }
};
