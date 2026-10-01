<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AppClock;
use PHPUnit\Framework\TestCase;

final class AppClockTest extends TestCase
{
    public function testTodayIsTheLocalCalendarDayNotTheUtcOne(): void
    {
        // 23:30 UTC del 14 → in Italia (estate, UTC+2) sono già le 01:30 del 15.
        $now = new \DateTimeImmutable('2026-07-14 23:30:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-07-15', (new AppClock('Europe/Rome'))->today($now)->format('Y-m-d'));
        self::assertSame('2026-07-14', (new AppClock('UTC'))->today($now)->format('Y-m-d'));
    }

    public function testTodayIsMidnight(): void
    {
        $today = (new AppClock('Europe/Rome'))->today(new \DateTimeImmutable('2026-01-10 15:45:12', new \DateTimeZone('UTC')));

        self::assertSame('00:00:00', $today->format('H:i:s'));
    }

    public function testADueDateOfTodayIsZeroDaysAwayEvenJustAfterLocalMidnight(): void
    {
        // Il bug: alle 00:30 locali "oggi" in UTC era ancora ieri e la scadenza di oggi risultava "a 1 giorno".
        $now = new \DateTimeImmutable('2026-07-14 22:30:00', new \DateTimeZone('UTC')); // 00:30 del 15 a Roma
        $dueDate = new \DateTimeImmutable('2026-07-15');

        $daysLeft = (int) (new AppClock('Europe/Rome'))->today($now)->diff($dueDate)->format('%r%a');

        self::assertSame(0, $daysLeft);
    }
}
