<?php

namespace App\Services;

/**
 * 一括インポート解析（spec §5「一括インポート解析」・cc_instructions v1.3 §3）。
 *
 * docs/EventImportDemo.jsx の parse() を「挙動を変えず」1:1で移植したもの。
 * 正規表現・状態機械・ノイズ辞書・境界判定を写経している。
 * 解析ロジックの改善はしない（改善余地に気づいても QUESTIONS.md へ回す・v1.3 §3-1）。
 */
class EventImportParser
{
    /** ノイズ見出し（この直後の実質行がツアー名候補）。jsx の NOISE をそのまま移植。 */
    private const NOISE = [
        'TOP', 'ARTIST', 'NEWS', 'SCHEDULE', 'CONCERT/STAGE', 'MOVIE',
        'RELEASE', '会社情報', 'OFFICIAL ACCOUNT', 'CONCERT', 'TICKET', 'GOODS', '公演情報',
        'SHARE', 'EN', 'JP', 'サービスガイドはこちら', '一覧へ戻る',
    ];

    // jsx の各正規表現（/u でユニコード・全角括弧をそのまま扱う）
    private const VENUE = '/^\[(.+?)\]\s*(.+)$/u';
    private const DATE = '/^(\d{4})\.(\d{2})\.(\d{2})（[^）]+）\s*(\d{1,2}:\d{2})?/u';
    private const TIME = '/^(\d{1,2}:\d{2})$/u';
    private const END = '/^(＜ご注意＞|※|一覧へ戻る|SHARE$)/u';
    private const HEAD = '/^(CONCERT|公演情報|CONCERT\/STAGE)$/u';

    /**
     * jsx parse() の移植。行分解→本文境界切出し→ツアー名特定→状態機械で行分類。
     *
     * @return array{tour: ?string, rows: array<int, array{kind:string, venue:string, date:string, time:string, raw:string}>, bodyRange: ?array{0:int,1:int,2:int}}
     */
    public function parse(string $text): array
    {
        // lines = text.split("\n").map(l => l.trim())
        $lines = array_map('trim', explode("\n", $text));

        // startIdx = 最初に会場行が現れた位置
        $startIdx = -1;
        foreach ($lines as $i => $l) {
            if (preg_match(self::VENUE, $l)) {
                $startIdx = $i;
                break;
            }
        }
        if ($startIdx === -1) {
            return ['tour' => null, 'rows' => [], 'bodyRange' => null];
        }

        // endIdx = startIdx より後で最初に本文終端が現れた位置。無ければ末尾。
        $endIdx = -1;
        foreach ($lines as $i => $l) {
            if ($i > $startIdx && preg_match(self::END, $l)) {
                $endIdx = $i;
                break;
            }
        }
        if ($endIdx === -1) {
            $endIdx = count($lines);
        }

        // ツアー名＝構造位置で特定（キーワード非依存）：
        // 見出し（CONCERT/公演情報/CONCERT/STAGE）直後の、ノイズでない5文字以上の実質行。
        $tour = null;
        for ($i = 0; $i < $startIdx && $tour === null; $i++) {
            if (preg_match(self::HEAD, $lines[$i])) {
                for ($j = $i + 1; $j < $startIdx; $j++) {
                    $t = $lines[$j];
                    if ($t === '' || in_array($t, self::NOISE, true) || preg_match(self::HEAD, $t)) {
                        continue;
                    }
                    if (mb_strlen($t) >= 5) {
                        $tour = $t;
                        break;
                    }
                }
            }
        }

        $rows = [];
        $venue = null;
        $date = null;
        $mode = null;
        for ($i = $startIdx; $i < $endIdx; $i++) {
            // line.replace(/[ 　\t]+/g," ").trim()（半角/全角スペース/タブを1つの半角に）
            $line = trim(preg_replace('/[ \x{3000}\t]+/u', ' ', $lines[$i]));
            if ($line === '' || $line === '公演日 開演時間') {
                continue;
            }

            if (preg_match(self::VENUE, $line, $m)) {
                $venue = $m[2];
                $mode = 'schedule';
                $rows[] = ['kind' => 'venue', 'venue' => $venue, 'date' => '', 'time' => '', 'raw' => $line];
                continue;
            }
            if ($line === 'アクセス情報') {
                $mode = 'access';
                continue;
            }
            if (preg_match(self::DATE, $line, $m)) {
                $date = "{$m[1]}-{$m[2]}-{$m[3]}";
                $mode = 'schedule';
                // 日付行末尾に開演時間が続く場合はそれも1公演として拾う（m[4]）
                if (isset($m[4]) && $m[4] !== '') {
                    $rows[] = ['kind' => 'show', 'venue' => $venue, 'date' => $date, 'time' => $m[4], 'raw' => $line];
                }
                continue;
            }
            // schedule モード中の単独時刻行＝同一日の別公演（昼夜）
            if ($mode === 'schedule' && preg_match(self::TIME, $line, $m)) {
                $rows[] = ['kind' => 'show', 'venue' => $venue, 'date' => $date, 'time' => $m[1], 'raw' => $line];
                continue;
            }
            if ($mode === 'access') {
                $rows[] = ['kind' => 'access', 'venue' => $venue, 'date' => '', 'time' => '', 'raw' => $line];
                continue;
            }
            // いずれにも当たらない行は捨てず unknown で保持（確認テーブルに出す）
            $rows[] = ['kind' => 'unknown', 'venue' => '', 'date' => '', 'time' => '', 'raw' => $line];
        }

        return ['tour' => $tour, 'rows' => $rows, 'bodyRange' => [$startIdx, $endIdx, count($lines)]];
    }

    /**
     * jsx の stats（useMemo）相当：show 行を events 候補へ写像する。
     * 確認テーブルへ渡す形（event_name はツアー名を全行へ適用）。
     *
     * @return array{tour: ?string, events: array<int, array{event_name:string, event_date:string, start_time:string, venue:string}>, unknown: array<int, string>}
     */
    public function extractEvents(string $text): array
    {
        $parsed = $this->parse($text);
        $tour = $parsed['tour'];

        $events = [];
        $unknown = [];
        foreach ($parsed['rows'] as $r) {
            if ($r['kind'] === 'show') {
                $events[] = [
                    'event_name' => $tour ?? '(ツアー名未検出)',
                    'event_date' => $r['date'],
                    'start_time' => $r['time'],
                    'venue' => $r['venue'],
                ];
            } elseif ($r['kind'] === 'unknown') {
                $unknown[] = $r['raw'];
            }
        }

        return ['tour' => $tour, 'events' => $events, 'unknown' => $unknown];
    }
}
