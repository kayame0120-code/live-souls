<?php

namespace Tests\Unit;

use App\Models\Attendance;
use PHPUnit\Framework\TestCase;

/**
 * 座席の自動合成（spec §5-8）: 「{block} {row}列 {number}番」空要素はスキップ。
 */
class SeatComposeTest extends TestCase
{
    public function test_全フィールドありの合成(): void
    {
        $this->assertSame(
            'アリーナB4 3列 15番',
            Attendance::composeSeatRaw('アリーナB4', '3', '15'),
        );
    }

    public function test_空要素はスキップされる(): void
    {
        $this->assertSame('アリーナB4', Attendance::composeSeatRaw('アリーナB4', null, null));
        $this->assertSame('3列 15番', Attendance::composeSeatRaw(null, '3', '15'));
        $this->assertSame('15番', Attendance::composeSeatRaw('', '', '15'));
    }

    public function test_全て空ならnull(): void
    {
        $this->assertNull(Attendance::composeSeatRaw(null, null, null));
        $this->assertNull(Attendance::composeSeatRaw('', '', ''));
    }
}
