<?php

namespace App\Services;

use App\Models\Venue;
use Illuminate\Support\Carbon;

/**
 * 貼り付け一括インポートの行パーサ（spec §5-10 [既定]）。
 * 各行から日付・会場・公演名の候補を抽出する。
 * 解析不能な要素は空欄のまま返す（無言で捨てない・spec §7）。
 */
class LotImportService
{
    /**
     * @return array<int, array{raw: string, event_date: ?string, venue_name: ?string, event_name: ?string}>
     */
    public function parse(string $text): array
    {
        $venueNames = Venue::pluck('name')->all();

        $rows = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$date, $rest] = $this->extractDate($line);
            [$venue, $rest] = $this->extractVenue($rest, $venueNames);

            $eventName = trim(preg_replace('/\s{2,}/u', ' ', $rest)) ?: null;

            $rows[] = [
                'raw' => $line,
                'event_date' => $date,
                'venue_name' => $venue,
                'event_name' => $eventName,
            ];
        }

        return $rows;
    }

    /**
     * 日付候補を抽出して行から取り除く。
     * 対応: YYYY/MM/DD・YYYY-MM-DD・YYYY.MM.DD・YYYY年M月D日・MM/DD・M月D日（年なしは今年）
     *
     * @return array{0: ?string, 1: string} [Y-m-d or null, 残り文字列]
     */
    private function extractDate(string $line): array
    {
        // 年あり
        if (preg_match('/(\d{4})[\/\-\.年](\d{1,2})[\/\-\.月](\d{1,2})日?/u', $line, $m)) {
            $date = $this->safeDate((int) $m[1], (int) $m[2], (int) $m[3]);
            if ($date) {
                return [$date, str_replace($m[0], ' ', $line)];
            }
        }

        // 年なし（今年と解釈。確認テーブルで修正可能）
        if (preg_match('/(?<!\d)(\d{1,2})[\/月](\d{1,2})日?(?!\d)/u', $line, $m)) {
            $date = $this->safeDate((int) now()->year, (int) $m[1], (int) $m[2]);
            if ($date) {
                return [$date, str_replace($m[0], ' ', $line)];
            }
        }

        return [null, $line];
    }

    /**
     * 既存会場名との部分一致（最長一致）で会場候補を抽出。
     *
     * @return array{0: ?string, 1: string}
     */
    private function extractVenue(string $line, array $venueNames): array
    {
        $matched = null;
        foreach ($venueNames as $name) {
            if ($name !== '' && mb_stripos($line, $name) !== false) {
                if ($matched === null || mb_strlen($name) > mb_strlen($matched)) {
                    $matched = $name;
                }
            }
        }

        if ($matched !== null) {
            return [$matched, str_ireplace($matched, ' ', $line)];
        }

        return [null, $line];
    }

    private function safeDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->format('Y-m-d');
    }
}
