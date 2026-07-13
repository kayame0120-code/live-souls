<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->text('name')->nullable()->change();
            $table->text('birth_date')->nullable()->change();
        });

        // 既存行の平文を暗号化（Eloquent不使用・冪等）
        DB::table('persons')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $update = [];

                if ($row->name !== null && ! str_starts_with($row->name, 'eyJ')) {
                    $update['name'] = Crypt::encryptString($row->name);
                }

                if ($row->birth_date !== null && ! str_starts_with($row->birth_date, 'eyJ')) {
                    $update['birth_date'] = Crypt::encryptString($row->birth_date);
                }

                if (! empty($update)) {
                    DB::table('persons')->where('id', $row->id)->update($update);
                }
            }
        });
    }

    public function down(): void
    {
        // 暗号文→平文の復号
        DB::table('persons')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $update = [];

                if ($row->name !== null && str_starts_with($row->name, 'eyJ')) {
                    $update['name'] = Crypt::decryptString($row->name);
                }

                if ($row->birth_date !== null && str_starts_with($row->birth_date, 'eyJ')) {
                    $update['birth_date'] = Crypt::decryptString($row->birth_date);
                }

                if (! empty($update)) {
                    DB::table('persons')->where('id', $row->id)->update($update);
                }
            }
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
        });
    }
};
