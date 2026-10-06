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

    public function testFullTankWithNonIncreasingKmIsNeverTheAnchor(): void
    {
        $rows = [
            self::row('2026-01-01', 10000, '40.000'),
            self::row('2026-01-10', 10000, '30.000'), // stesso odometro (refuso)
            self::row('2026-01-20', 9800, '30.000'),  // odometro all'indietro
        ];

        self::assertSame([], $this->consumption->intervals($rows, 'diesel'));
    }

    public function testZeroKmTypoDoesNotCorruptTheNextInterval(): void
    {
        // Prima il pieno a km 0 diventava l'ancora: 10600 km su 40 L = 265 km/l. Ora la riga a 0 conta come
        // un parziale: l'intervallo 10000 → 10600 si chiude con i litri di entrambi i pieni (80 L).
        $rows = [
            self::row('2026-01-01', 10000, '40.000'),
            self::row('2026-01-10', 0, '40.000'),
            self::row('2026-01-20', 10600, '40.000'),
        ];

        $intervals = $this->consumption->intervals($rows, 'diesel');

        self::assertSame([['closedOn' => '2026-01-20', 'km' => 600, 'liters' => 80.0]], $intervals);
        self::assertSame(7.5, $this->consumption->kmPerLiter($intervals));
    }

    public function testValidFullTanksAfterANonIncreasingOneStillMeasureFromTheLastValidAnchor(): void
    {
        $rows = [
            self::row('2026-01-01', 10000, '40.000'),
            self::row('2026-01-10', 9000, '20.000'),  // all'indietro: parziale
            self::row('2026-01-20', 10500, '30.000'), // chiude 10000 → 10500 con 50 L
            self::row('2026-02-01', 11100, '40.000'), // l'ancora è quello a 10500
        ];

        $intervals = $this->consumption->intervals($rows, 'diesel');

        self::assertSame([
            ['closedOn' => '2026-01-20', 'km' => 500, 'liters' => 50.0],
            ['closedOn' => '2026-02-01', 'km' => 600, 'liters' => 40.0],
        ], $intervals);
        self::assertSame(12.22, $this->consumption->kmPerLiter($intervals));
    }

    public function testTypoTowardsAHigherOdometerIsImplausibleAndDoesNotBecomeTheAnchor(): void
    {
        // Refuso verso l'alto (100600 invece di 10600): 90600 km / 40 L = 2265 km/l, oltre la soglia.
        // Il pieno a 100600 non è né un intervallo né un'ancora: i suoi 40 L si sommano a quelli del pieno
        // successivo (10000 → 11200 su 80 L = 15 km/l) e il pieno dopo misura ancora da 11200.
        $rows = [
            self::row('2026-01-01', 10000, '40.000'),
            self::row('2026-01-10', 100600, '40.000'),
            self::row('2026-01-20', 11200, '40.000'),
            self::row('2026-02-01', 11800, '40.000'),
        ];

        $intervals = $this->consumption->intervals($rows, 'diesel');

        self::assertSame([
            ['closedOn' => '2026-01-20', 'km' => 1200, 'liters' => 80.0],
            ['closedOn' => '2026-02-01', 'km' => 600, 'liters' => 40.0],
        ], $intervals);
        self::assertSame(15.0, $this->consumption->kmPerLiter($intervals));
    }

    public function testIntervalExactlyAtTheMaxPlausibleKmPerLiterIsCountedOneOverIsNot(): void
    {
        $atLimit = [
            self::row('2026-01-01', 10000, '10.000'),
            self::row('2026-01-10', 11000, '10.000'), // 1000 km / 10 L = 100 km/l: ammesso
        ];
        self::assertSame(
            [['closedOn' => '2026-01-10', 'km' => 1000, 'liters' => 10.0]],
            $this->consumption->intervals($atLimit, 'diesel'),
        );

        $overLimit = [
            self::row('2026-01-01', 10000, '10.000'),
            self::row('2026-01-10', 11001, '10.000'), // 100,1 km/l: scartato
        ];
        self::assertSame([], $this->consumption->intervals($overLimit, 'diesel'));
    }

    public function testNonIncreasingFullTankDoesNotBreakTheBiFuelRule(): void
    {
        $rows = [
            self::row('2026-01-01', 10000, '40.000', fuel: 'gasoline'),
            self::row('2026-01-05', 10300, '20.000', fuel: 'lpg'),
            self::row('2026-01-08', 5000, '30.000', fuel: 'gasoline'),  // all'indietro: parziale, intervallo comunque misto
            self::row('2026-01-20', 11000, '20.000', fuel: 'gasoline'), // misto: scartato, diventa l'ancora
            self::row('2026-02-01', 11500, '25.000', fuel: 'gasoline'),
        ];

        self::assertSame(
            [['closedOn' => '2026-02-01', 'km' => 500, 'liters' => 25.0]],
            $this->consumption->intervals($rows, 'gasoline'),
        );
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
