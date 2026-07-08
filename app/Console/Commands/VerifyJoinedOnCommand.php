<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * joined_month→joined_on 変換の機械検証（spec §10-2 手順2 / 指示書 §1-1-3）。
 * DROP 前に実行し、件数・値の突合結果を出力する。目視照合を人間に求めない。
 */
class VerifyJoinedOnCommand extends Command
{
    protected $signature = 'genba:verify-joined-on';

    protected $description = 'joined_month → joined_on 変換の件数・値を機械検証する';

    public function handle(): int
    {
        if (! Schema::hasColumn('fc_memberships', 'joined_month')) {
            $this->error('joined_month カラムが存在しません（既にDROP済み？）。検証はDROP前に実行してください。');
            return self::FAILURE;
        }

        if (! Schema::hasColumn('fc_memberships', 'joined_on')) {
            $this->error('joined_on カラムが存在しません。変換マイグレーション未実行です。');
            return self::FAILURE;
        }

        $sourceCount = DB::table('fc_memberships')->whereNotNull('joined_month')->count();
        $convertedCount = DB::table('fc_memberships')->whereNotNull('joined_on')->count();

        $this->line("joined_month 非null件数: {$sourceCount}");
        $this->line("joined_on    非null件数: {$convertedCount}");

        if ($sourceCount !== $convertedCount) {
            $this->error('件数不一致。DROPを中止してください。');
            return self::FAILURE;
        }

        $rows = DB::table('fc_memberships')
            ->whereNotNull('joined_month')
            ->get(['id', 'joined_month', 'joined_on']);

        $mismatch = 0;
        foreach ($rows as $r) {
            $expected = $r->joined_month . '-01';
            $ok = $r->joined_on === $expected;
            $this->line(sprintf(
                'id=%d  joined_month="%s"  joined_on="%s"  expected="%s"  %s',
                $r->id, $r->joined_month, $r->joined_on, $expected, $ok ? 'OK' : 'MISMATCH'
            ));
            if (! $ok) {
                $mismatch++;
            }
        }

        if ($mismatch > 0) {
            $this->error("{$mismatch}件の値不一致。DROPを中止してください。");
            return self::FAILURE;
        }

        $this->info("検証通過: {$sourceCount}件すべて一致。DROP可能です。");
        return self::SUCCESS;
    }
}
