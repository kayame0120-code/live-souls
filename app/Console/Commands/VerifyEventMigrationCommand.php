<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * attendances.event_id 移行の機械検証（spec §8 手順4 / 指示書T2-4）。
 * DROP前に実行し、各参戦の公演名・日付・会場が events 経由で一致するか突合する。
 * 目視照合を人間に求めない。
 */
class VerifyEventMigrationCommand extends Command
{
    protected $signature = 'genba:verify-event-migration';

    protected $description = 'attendances の event_id 移行（旧3カラム↔events）を機械検証する';

    public function handle(): int
    {
        foreach (['event_name', 'event_date', 'venue_id', 'event_id'] as $col) {
            if (! Schema::hasColumn('attendances', $col)) {
                $this->error("attendances.{$col} が存在しません。検証はDROP前に実行してください。");
                return self::FAILURE;
            }
        }

        $total = DB::table('attendances')->count();
        $nullEventId = DB::table('attendances')->whereNull('event_id')->count();
        $this->line("参戦総数: {$total}");
        $this->line("event_id 未設定: {$nullEventId}");

        if ($nullEventId > 0) {
            $this->error('event_id 未設定の参戦があります。移行未完了。');
            return self::FAILURE;
        }

        $rows = DB::table('attendances')
            ->join('events', 'attendances.event_id', '=', 'events.id')
            ->get([
                'attendances.id as id',
                'attendances.event_name as a_name',
                'attendances.event_date as a_date',
                'attendances.venue_id as a_venue',
                'events.event_name as e_name',
                'events.event_date as e_date',
                'events.venue_id as e_venue',
            ]);

        $mismatch = 0;
        foreach ($rows as $r) {
            $ok = $r->a_name === $r->e_name
                && substr((string) $r->a_date, 0, 10) === substr((string) $r->e_date, 0, 10)
                && (int) $r->a_venue === (int) $r->e_venue;

            $this->line(sprintf(
                'id=%d  name[%s]  date[%s→%s]  venue[%s→%s]  %s',
                $r->id,
                $r->a_name === $r->e_name ? 'OK' : 'NG',
                substr((string) $r->a_date, 0, 10),
                substr((string) $r->e_date, 0, 10),
                $r->a_venue,
                $r->e_venue,
                $ok ? 'OK' : 'MISMATCH',
            ));
            if (! $ok) {
                $mismatch++;
            }
        }

        $eventCount = DB::table('events')->count();
        $this->line("events 生成数: {$eventCount}");

        if ($mismatch > 0) {
            $this->error("{$mismatch}件の不一致。DROPを中止してください。");
            return self::FAILURE;
        }

        $this->info("検証通過: {$total}件すべて一致。旧3カラムのDROP可能です。");
        return self::SUCCESS;
    }
}
