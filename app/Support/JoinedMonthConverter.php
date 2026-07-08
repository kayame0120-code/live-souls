<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * v1.0 joined_month ("YYYY-MM") → v1.1 joined_on ("YYYY-MM-01") 変換。
 * spec §10-1: YYYY-MM形式でない値が存在したら変換せず停止する。
 */
class JoinedMonthConverter
{
    public const FORMAT_PATTERN = '/^\d{4}-\d{2}$/';

    /**
     * @throws InvalidArgumentException 形式不一致（呼び出し側で捕捉し QUESTIONS.md へ隔離）
     */
    public static function toDate(string $joinedMonth): string
    {
        if (! preg_match(self::FORMAT_PATTERN, $joinedMonth)) {
            throw new InvalidArgumentException(
                "joined_month が YYYY-MM 形式ではありません: \"{$joinedMonth}\""
            );
        }

        // 月の妥当性（01〜12）も機械検証する
        $month = (int) substr($joinedMonth, 5, 2);
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                "joined_month の月が不正です: \"{$joinedMonth}\""
            );
        }

        return $joinedMonth . '-01';
    }
}
