<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\FuelConsumption;
use PHPUnit\Framework\TestCase;

final class FuelConsumptionTest extends TestCase
{
    private FuelConsumption $consumption;

    protected function setUp(): void
    {
        $this->consumption = new FuelConsumption();
    }

    public function testConsecutiveFullTanksFormOneIntervalEach(): void
    {
        $rows = [
            self::row('2026-01-01', 50000, '40.000'),
            self::row('2026-01-15', 50500, '35.000'),
            self::row('2026-02-01', 51000, '30.000'),
        ];

        $intervals = $this->consumption->intervals($rows, 'diesel');

        self::assertSame([
            ['closedOn' => '2026-01-15', 'km' => 500, 'liters' => 35.0],
            ['closedOn' => '2026-02-01', 'km' => 500, 'liters' => 30.0],
        ], $intervals);
        self::assertSame(15.38, $this->consumption->kmPerLiter($intervals));
    }

    public function testPartialRefuelingsInBetweenAreSummed(): void
    {
        $rows = [
            self::row('2026-01-01', 10000, '40.000'),
            self::row('2026-01-05', 10200, '10.000', full: false),
            self::row('2026-01-10', 10600, '20.000'),
        ];

        self::assertSame(
            [['closedOn' => '2026-01-10', 'km' => 600, 'liters' => 30.0]],
            $this->consumption->intervals($rows, 'diesel'),
        );
    }

    public function testASingleFullTankHasNoInterval(): void
    {
        $intervals = $this->consumption->intervals([self::row('2026-01-01', 10000, '40.000')], 'diesel');

        self::assertSame([], $intervals);
        self::assertNull($this->consumption->kmPerLiter($intervals));
    }

    public function testOtherFuelInBetweenDiscardsTheIntervalAndTheNextFullTankIsTheNewAnchor(): void
    {
        $rows = [
            self::row('2026-01-01', 10000, '40.000', fuel: 'gasoline'),
            self::row('2026-01-05', 10300, '25.000', fuel: 'lpg'),
            self::row('2026-01-10', 10600, '30.000', fuel: 'gasoline'), // intervallo misto: scartato
            self::row('2026-01-20', 11000, '20.000', fuel: 'gasoline'), // ancora = pieno precedente
        ];

        self::assertSame(
            [['closedOn' => '2026-01-20', 'km' => 400, 'liters' => 20.0]],
            $this->consumption->intervals($rows, 'gasoline'),
        );
    }

    public function testNonPositiveDeltaKmIsDiscarded(): void
    {
        $rows = [
            self::row('2026-01-01', 10000, '40.000'),
            self::row('2026-01-10', 10000, '30.000'), // stesso odometro (refuso)
            self::row('2026-01-20', 9800, '30.000'),  // odometro all'indietro
        ];

        self::assertSame([], $this->consumption->intervals($rows, 'diesel'));
    }

    public function testClosedOnIsTheDateOfTheClosingFullTankEvenWithATimePart(): void
    {
        $rows = [
            self::row('2025-12-28', 10000, '40.000'),
            self::row('2026-01-03 00:00:00', 10500, '30.000'),
        ];

        self::assertSame('2026-01-03', $this->consumption->intervals($rows, 'diesel')[0]['closedOn']);
    }

    /**
     * @return array{km: int, liters: string, full_tank: bool, fuel_type: string, refueled_at: string}
     */
    private static function row(string $date, int $km, string $liters, bool $full = true, string $fuel = 'diesel'): array
    {
        return ['km' => $km, 'liters' => $liters, 'full_tank' => $full, 'fuel_type' => $fuel, 'refueled_at' => $date];
    }
}
