<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idol_group_id')->constrained('idol_groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('color_name');
            $table->string('color_hex')->nullable();
            $table->string('source_type');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
