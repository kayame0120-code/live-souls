<?php

namespace Tests\Unit;

use App\Models\Person;
use Carbon\Carbon;
use Tests\TestCase;

class PersonAgeTest extends TestCase
{
    public function test_通常の年齢計算(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-08'));

        $person = new Person(['birth_date' => '2000-03-15']);
        $this->assertSame(26, $person->age());
    }

    public function test_誕生日当日(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15'));

        $person = new Person(['birth_date' => '2000-03-15']);
        $this->assertSame(26, $person->age());
    }

    public function test_誕生日前日(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-14'));

        $person = new Person(['birth_date' => '2000-03-15']);
        $this->assertSame(25, $person->age());
    }

    public function test_うるう年2月29日生まれ_平年は3月1日で加齢(): void
    {
        // 2025年は平年（2月29日がない）
        Carbon::setTestNow(Carbon::parse('2025-02-28'));
        $person = new Person(['birth_date' => '2000-02-29']);
        $this->assertSame(24, $person->age());

        Carbon::setTestNow(Carbon::parse('2025-03-01'));
        $this->assertSame(25, $person->age());
    }

    public function test_うるう年2月29日生まれ_うるう年は2月29日で加齢(): void
    {
        // 2024年はうるう年
        Carbon::setTestNow(Carbon::parse('2024-02-28'));
        $person = new Person(['birth_date' => '2000-02-29']);
        $this->assertSame(23, $person->age());

        Carbon::setTestNow(Carbon::parse('2024-02-29'));
        $this->assertSame(24, $person->age());
    }

    public function test_birth_dateがnullならnull(): void
    {
        $person = new Person(['birth_date' => null]);
        $this->assertNull($person->age());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
