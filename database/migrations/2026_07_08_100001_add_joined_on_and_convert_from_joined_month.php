<?php

use App\Support\JoinedMonthConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.0→v1.1 変換マイグレーション（spec §10-2 手順2）。
 * joined_on を追加し、既存 joined_month をPHP側で変換コピーする。
 * 形式不一致が1件でもあれば例外で中断（トランザクション巻き戻し）。
 * 変換後にマイグレーション内で件数・値の機械検証を行い、不一致なら中断する。
 * DROP は次のマイグレーションで行う（検証を挟むため分離）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->date('joined_on')->nullable()->after('password');
        });

        // 既存データの変換（クエリビルダのみ・生SQL不使用）
        $rows = DB::table('fc_memberships')
            ->whereNotNull('joined_month')
            ->get(['id', 'joined_month']);

        foreach ($rows as $row) {
            // 形式不一致は InvalidArgumentException → マイグレーション失敗として停止
            $converted = JoinedMonthConverter::toDate($row->joined_month);

            DB::table('fc_memberships')
                ->where('id', $row->id)
                ->update(['joined_on' => $converted]);
        }

        // 機械検証: 非null件数の一致 + 各行の値一致（DROP前の安全弁）
        $sourceCount = DB::table('fc_memberships')->whereNotNull('joined_month')->count();
        $convertedCount = DB::table('fc_memberships')->whereNotNull('joined_on')->count();

        if ($sourceCount !== $convertedCount) {
            throw new RuntimeException(
                "joined_month→joined_on 変換の件数不一致: source={$sourceCount}, converted={$convertedCount}"
            );
        }

        $mismatched = DB::table('fc_memberships')
            ->whereNotNull('joined_month')
            ->get(['id', 'joined_month', 'joined_on'])
            ->filter(fn ($r) => $r->joined_on !== $r->joined_month . '-01');

        if ($mismatched->isNotEmpty()) {
            throw new RuntimeException(
                'joined_month→joined_on 変換の値不一致: ' . $mismatched->pluck('id')->implode(',')
            );
        }
    }

    public function down(): void
    {
        Schema::table('fc_memberships', function (Blueprint $table) {
            $table->dropColumn('joined_on');
        });
    }
};
