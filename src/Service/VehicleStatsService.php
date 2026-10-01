<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Vehicle;
use App\Enum\FuelType;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Aggregati per la dashboard del veicolo.
 *
 * Le query SQL sono native per evitare il costo ORM su aggregazioni pesanti.
 */
final class VehicleStatsService
{
    /** Le statistiche costano ~9 query: si tengono in cache, invalidate a ogni scrittura che le alimenta. */
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly Connection $db,
        private readonly CacheInterface $cache,
        private readonly FuelConsumption $consumption,
    ) {
    }

    /** Toglie dalla cache le statistiche del veicolo (vedi VehicleStatsCacheInvalidator). */
    public function invalidate(int $vehicleId): void
    {
        $this->cache->delete(self::cacheKey($vehicleId));
    }

    private static function cacheKey(int $vehicleId): string
    {
        return 'vehicle_stats.'.$vehicleId;
    }

    /**
     * Statistiche complete per un veicolo.
     *
     * @return array{
     *     consumption: array<string, float|null>,   // per fuel type → km/l
     *     totals: array{cost: string, refuelings: int, maintenances: int, expenses: int},
     *     currentKm: int,
     *     kmDriven: int,
     *     costPerKm: float|null,
     * }
     */
    public function compute(Vehicle $vehicle): array
    {
        $vehicleId = (int) $vehicle->getId();

        return $this->cache->get(self::cacheKey($vehicleId), function (ItemInterface $item) use ($vehicle): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->computeUncached($vehicle);
        });
    }

    /**
     * @return array{
     *     consumption: array<string, float|null>,
     *     totals: array{cost: string, refuelings: int, maintenances: int, expenses: int},
     *     currentKm: int,
     *     kmDriven: int,
     *     costPerKm: float|null,
     * }
     */
    private function computeUncached(Vehicle $vehicle): array
    {
        $vehicleId = (int) $vehicle->getId();

        $currentKm = $this->currentKm($vehicle);
        $kmDriven = max(0, $currentKm - $vehicle->getInitialKm());
        $totalCost = $this->totalCost($vehicleId);

        return [
            'consumption' => $this->averageConsumptionPerFuel($vehicleId, $vehicle->getAllFuelTypes()),
            'totals' => [
                'cost' => $totalCost,
                'refuelings' => $this->countByTable($vehicleId, 'refuelings'),
                'maintenances' => $this->countByTable($vehicleId, 'maintenances'),
                'expenses' => $this->countByTable($vehicleId, 'expenses'),
            ],
            'currentKm' => $currentKm,
            'kmDriven' => $kmDriven,
            'costPerKm' => $kmDriven > 0 ? round((float) $totalCost / $kmDriven, 4) : null,
        ];
    }

    /**
     * Km attuali = odometro più alto inserito su rifornimenti/manutenzioni,
     * mai sotto i km iniziali del veicolo.
     */
    public function currentKm(Vehicle $vehicle): int
    {
        return max($this->maxRecordedKm((int) $vehicle->getId()), $vehicle->getInitialKm());
    }

    // ----- internals -----

    /**
     * Media km/l per ogni fuel type del veicolo, con l'algoritmo fill-to-fill di {@see FuelConsumption}.
     *
     * @param list<FuelType> $fuels
     * @return array<string, float|null>  key = fuel_type value
     */
    private function averageConsumptionPerFuel(int $vehicleId, array $fuels): array
    {
        // Una sola lettura ordinata di tutti i rifornimenti: servono anche quelli dell'altro carburante.
        /** @var list<array{km: string|int, liters: string, full_tank: string|int|bool, fuel_type: string, refueled_at: string, id: string|int}> $rows */
        $rows = $this->db->fetchAllAssociative(
            'SELECT km, liters, full_tank, fuel_type, refueled_at, id
             FROM refuelings
             WHERE vehicle_id = :vid
             ORDER BY refueled_at ASC, km ASC, id ASC',
            ['vid' => $vehicleId],
        );

        $result = [];
        foreach ($fuels as $fuel) {
            $result[$fuel->value] = $this->consumption->kmPerLiter($this->consumption->intervals($rows, $fuel->value));
        }

        return $result;
    }

    /** Odometro più alto tra rifornimenti e manutenzioni (0 se nessun record). */
    private function maxRecordedKm(int $vehicleId): int
    {
        return (int) ($this->db->fetchOne(
            'SELECT GREATEST(
                COALESCE((SELECT MAX(km) FROM refuelings WHERE vehicle_id = :vid), 0),
                COALESCE((SELECT MAX(km) FROM maintenances WHERE vehicle_id = :vid), 0)
             )',
            ['vid' => $vehicleId],
        ) ?: 0);
    }

    private function totalCost(int $vehicleId): string
    {
        $cost = $this->db->fetchOne(
            'SELECT COALESCE(
                (SELECT SUM(total_cost) FROM refuelings WHERE vehicle_id = :vid), 0
             ) + COALESCE(
                (SELECT SUM(cost) FROM maintenances WHERE vehicle_id = :vid AND cost IS NOT NULL), 0
             ) + COALESCE(
                (SELECT SUM(amount) FROM expenses WHERE vehicle_id = :vid), 0
             )',
            ['vid' => $vehicleId],
        );
        return number_format((float) $cost, 2, '.', '');
    }

    private function countByTable(int $vehicleId, string $table): int
    {
        // Whitelist defensive: solo tabelle previste
        if (!in_array($table, ['refuelings', 'maintenances', 'expenses'], true)) {
            throw new \InvalidArgumentException("Unexpected table: $table");
        }
        return (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM $table WHERE vehicle_id = :vid",
            ['vid' => $vehicleId],
        );
    }
}
