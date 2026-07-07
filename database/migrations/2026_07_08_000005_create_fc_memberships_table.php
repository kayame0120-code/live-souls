<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fc_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('identity_groups')->cascadeOnDelete();
            $table->string('artist_name');
            $table->string('club_name')->nullable();
            $table->string('member_no')->nullable();
            $table->text('login_id')->nullable();
            $table->text('password')->nullable();
            $table->string('joined_month')->nullable();
            $table->string('renewal_cycle')->nullable();
            $table->string('oshi_color')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fc_memberships');
    }
};
