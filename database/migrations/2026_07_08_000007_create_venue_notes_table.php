<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('lodging')->nullable();
            $table->string('transport_cost')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'venue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_notes');
    }
};
