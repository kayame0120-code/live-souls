<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name');
            $table->date('event_date');
            $table->time('open_time')->nullable();
            $table->time('start_time')->nullable();
            $table->string('seat_raw')->nullable();
            $table->string('seat_block')->nullable();
            $table->string('seat_row')->nullable();
            $table->string('seat_number')->nullable();
            $table->string('status')->default('attended');
            $table->string('companion')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
