<?php

namespace Tests\Unit;

use App\Services\EventImportParser;
use PHPUnit\Framework\TestCase;

/**
 * 一括インポート解析の jsx 一致検証（cc_instructions v1.3 §7 T1／spec §5「一括インポート解析」）。
 * docs/EventImportDemo.jsx の parse() と挙動一致であることを、同一 SAMPLE で突合する。
 * パーサはDB非依存の純関数なので Unit（RefreshDatabase不要）。
 */
class EventImportParserTest extends TestCase
{
    private EventImportParser $parser;

    /** jsx の SAMPLE を1文字も変えず移植（\t=タブ, 全角括弧）。 */
    private const SAMPLE = "TOP\n"
        . "ARTIST\n"
        . "NEWS\n"
        . "公演情報\n"
        . "CONCERT/STAGE\n"
        . "CONCERT\n"
        . "なにわ男子 1st DOME LIVE 'VoyAGE'\n"
        . "なにわ男子\n"
        . "SCHEDULE\n"
        . "TICKET\n"
        . "GOODS\n"
        . "SCHEDULE\n"
        . "[大阪府] 京セラドーム大阪\n"
        . "公演日\t開演時間\n"
        . "2026.11.14（土）\t14:00\n"
        . "18:30\n"
        . "2026.11.15（日）\t14:00\n"
        . "アクセス情報\n"
        . "JR大正駅より徒歩7分\n"
        . "[東京都] 東京ドーム\n"
        . "公演日\t開演時間\n"
        . "2026.11.28（土）\t14:00\n"
        . "18:30\n"
        . "2026.11.29（日）\t14:00\n"
        . "アクセス情報\n"
        . "JR水道橋駅より徒歩5分\n"
        . "\n"
        . "＜ご注意＞\n"
        . "※公演スケジュールは変更となる場合があります。\n"
        . "一覧へ戻る\n"
        . "SHARE\n"
        . "TOP";

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new EventImportParser();
    }

    public function test_T1_SAMPLE抽出がjsxデモと一致する(): void
    {
        $result = $this->parser->extractEvents(self::SAMPLE);

        // ツアー名は構造位置で検出（見出し直後・ノイズ外・5文字以上）
        $this->assertSame("なにわ男子 1st DOME LIVE 'VoyAGE'", $result['tour']);

        // 件数：show 行＝6公演
        $this->assertCount(6, $result['events']);

        // 各行の date / start_time / venue が jsx と完全一致（順序込み）
        $expected = [
            ['2026-11-14', '14:00', '京セラドーム大阪'],
            ['2026-11-14', '18:30', '京セラドーム大阪'],
            ['2026-11-15', '14:00', '京セラドーム大阪'],
            ['2026-11-28', '14:00', '東京ドーム'],
            ['2026-11-28', '18:30', '東京ドーム'],
            ['2026-11-29', '14:00', '東京ドーム'],
        ];
        foreach ($expected as $i => [$date, $time, $venue]) {
            $this->assertSame($date, $result['events'][$i]['event_date'], "row {$i} date");
            $this->assertSame($time, $result['events'][$i]['start_time'], "row {$i} start_time");
            $this->assertSame($venue, $result['events'][$i]['venue'], "row {$i} venue");
            // event_name は全行にツアー名を適用
            $this->assertSame($result['tour'], $result['events'][$i]['event_name'], "row {$i} name");
        }
    }

    public function test_T2_同日複数時刻は別公演行に分かれる(): void
    {
        $text = "公演情報\nＸツアー 2026\n[大阪府] 大阪城ホール\n公演日\t開演時間\n2026.07.29（水）\t13:30\n18:00";
        $result = $this->parser->extractEvents($text);

        $this->assertCount(2, $result['events']);
        $this->assertSame('13:30', $result['events'][0]['start_time']);
        $this->assertSame('18:00', $result['events'][1]['start_time']);
        $this->assertSame('2026-07-29', $result['events'][0]['event_date']);
        $this->assertSame('2026-07-29', $result['events'][1]['event_date']);
        $this->assertSame('大阪城ホール', $result['events'][0]['venue']);
    }

    public function test_ノイズ行が本文境界で遮断される(): void
    {
        // 会場行の前後（ナビ・フッター）は events 化されない
        $result = $this->parser->extractEvents(self::SAMPLE);
        foreach ($result['events'] as $e) {
            $this->assertNotSame('TOP', $e['venue']);
            $this->assertNotSame('SHARE', $e['event_name']);
        }
        // SAMPLE では未解析行は発生しない（access はアクセス情報として吸収）
        $this->assertSame([], $result['unknown']);
    }

    public function test_未解析行は捨てず保持される(): void
    {
        // 会場行はあるが分類不能な行を混ぜる
        $text = "[東京都] 東京ドーム\n意味不明なメモ行";
        $result = $this->parser->extractEvents($text);

        $this->assertContains('意味不明なメモ行', $result['unknown']);
    }

    public function test_会場行が無ければ空を返す(): void
    {
        $result = $this->parser->extractEvents("ただのテキスト\n見出しもない");
        $this->assertSame([], $result['events']);
        $this->assertNull($result['tour']);
    }
}
