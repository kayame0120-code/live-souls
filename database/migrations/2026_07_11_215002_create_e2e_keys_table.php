<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e2e_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('wrapped_master_key_pw');
            $table->string('pw_salt');
            $table->text('wrapped_master_key_rk');
            $table->string('rk_salt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e2e_keys');
    }
};
