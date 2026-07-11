<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tour 逆生成の機械検証（spec §8手順3・指示書v1.4 §2-2 / U1・U3）。
 * NOT NULL 化・event_name DROP の前に実行し、
 * ①全 events に tour_id が非NULL ②同一 event_name は同一 tour に集約
 * ③旧 event_name の値が tours.name 側に保存されている ことを突合する。
 */
class VerifyTourMigrationCommand extends Command
{
    protected $signature = 'genba:verify-tour-migration';

    protected $description = 'events→tours 逆生成（tour_id/集約/値保全）を機械検証する';

    public function handle(): int
    {
        if (! Schema::hasColumn('events', 'tour_id')) {
            $this->error('events.tour_id が存在しません。移行①が未実行です。');
            return self::FAILURE;
        }

        $total = DB::table('events')->count();
        $nullCount = DB::table('events')->whereNull('tour_id')->count();
        $this->line("events 総数: {$total}");
        $this->line("tour_id 未設定: {$nullCount}");

        if ($nullCount > 0) {
            $this->error('tour_id 未設定の events があります。NOT NULL化・DROPを中止してください。');
            return self::FAILURE;
        }

        // event_name がまだ存在する場合のみ、集約と値保全を突合
        if (Schema::hasColumn('events', 'event_name')) {
            $distinctNames = DB::table('events')->distinct()->pluck('event_name');
            $tourCount = DB::table('tours')->count();
            $this->line("distinct event_name: {$distinctNames->count()} / tours: {$tourCount}");

            $mismatch = 0;
            foreach ($distinctNames as $name) {
                // 同一 event_name の events が単一 tour に集約されているか
                $tourIds = DB::table('events')->where('event_name', $name)->distinct()->pluck('tour_id');
                $nameInTours = DB::table('tours')->where('name', $name)->exists();
                $ok = $tourIds->count() === 1 && $nameInTours;
                if (! $ok) {
                    $mismatch++;
                    $this->line("  MISMATCH: \"{$name}\" tours={$tourIds->count()} nameSaved=" . ($nameInTours ? 'yes' : 'no'));
                }
            }
            if ($mismatch > 0) {
                $this->error("{$mismatch}件の集約/値保全の不一致。DROPを中止してください。");
                return self::FAILURE;
            }
            $this->info("検証通過: 全{$distinctNames->count()}ツアーが集約・値保全とも一致。event_name DROP 可能です。");
        } else {
            $this->info("検証通過: 全 events に tour_id が非NULL（event_name は既にDROP済み）。");
        }

        return self::SUCCESS;
    }
}
