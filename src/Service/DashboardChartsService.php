<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\VehicleRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Serie mensili per i grafici, con due soli perimetri possibili:
 * - dashboard ({@see self::compute()}): SOLO i veicoli di proprietà dell'utente nell'org attiva
 *   (archiviati esclusi), come i totali e le scadenze della dashboard. Gli id vengono solo da
 *   {@see VehicleRepository::findOwnedByUserInOrganization}, l'unico punto in cui stanno la regola
 *   di proprietà e lo scoping per org;
 * - singolo veicolo ({@see self::computeForVehicle()}): il veicolo del dettaglio, archiviato o no.
 *   Lo scoping per org e il controllo VEHICLE_VIEW spettano al chiamante.
 * Non c'è un ingresso pubblico che accetti una lista arbitraria di veicoli.
 *
 * Le aggregazioni sono SQL nativo (raggruppamento per mese con DATE_FORMAT, che il DQL non
 * esprime) in un numero costante di query, indipendente dai veicoli.
 *
 * Nessuna cache applicativa: il dato della dashboard è per utente e la sua invalidazione
 * dipenderebbe da chi è proprietario di ogni veicolo toccato, quello del veicolo varia con la
 * finestra e il mese corrente; le query aggregate su indici (vehicle_id, data) costano poco e lato
 * client React Query evita le richieste ripetute.
 *
 * Gli importi si sommano in centesimi interi (niente errori di arrotondamento dei float) e si
 * restituiscono come stringhe a 2 decimali, come il resto delle API.
 *
 * @phpstan-import-type FillInterval from FuelConsumption
 * @phpstan-import-type RefuelingRow from FuelConsumption
 *
 * @phpstan-type MonthSpending array{refuelings: string, maintenances: string, expenses: string, total: string}
 * @phpstan-type MonthPoint array{month: string, spending: MonthSpending, kmDriven: int, consumption: array<string, float|null>}
 * @phpstan-type DashboardCharts array{
 *     from: string,
 *     to: string,
 *     fuelTypes: list<string>,
 *     months: list<MonthPoint>,
 *     spendingByCategory: list<array{category: string, amount: string}>,
 *     totals: array{spending: string, kmDriven: int},
 * }
 */
final class DashboardChartsService
{
    public const DEFAULT_MONTHS = 12;
    public const MAX_MONTHS = 24;

    /** Chiavi di `spendingByCategory` per rifornimenti e manutenzioni (le spese usano ExpenseCategory). */
    private const CATEGORY_FUEL = 'fuel';
    private const CATEGORY_MAINTENANCE = 'maintenance';

    /** Marcatore delle letture d'odometro precedenti alla finestra (base dei km cumulati). */
    private const BEFORE_WINDOW = 'before';

    public function __construct(
        private readonly Connection $db,
        private readonly VehicleRepository $vehicleRepo,
        private readonly AppClock $clock,
        private readonly FuelConsumption $consumption,
    ) {
    }

    /**
     * Grafici della dashboard: veicoli di proprietà dell'utente nell'org, archiviati esclusi.
     *
     * @return DashboardCharts
     */
    public function compute(User $user, Organization $org, int $months): array
    {
        return $this->computeForVehicles($this->vehicleRepo->findOwnedByUserInOrganization($user, $org), $months);
    }

    /**
     * Grafici di un solo veicolo (anche archiviato). Il servizio non autorizza: il chiamante deve
     * aver già caricato il veicolo nell'org attiva e verificato VEHICLE_VIEW.
     *
     * @return DashboardCharts
     */
    public function computeForVehicle(Vehicle $vehicle, int $months): array
    {
        return $this->computeForVehicles([$vehicle], $months);
    }

    /**
     * Serie pronte per JSON: `consumption` è una mappa carburante → valore e, vuota (nessun
     * veicolo), va serializzata come `{}` e non come lista `[]`.
     *
     * @param DashboardCharts $charts
     * @return array<string, mixed>
     */
    public static function toJson(array $charts): array
    {
        $charts['months'] = array_map(
            static fn (array $point): array => [...$point, 'consumption' => (object) $point['consumption']],
            $charts['months'],
        );

        return $charts;
    }

    /**
     * Grafici degli ultimi `$months` mesi, mese corrente (nel fuso dell'istanza) incluso.
     * Tutti i mesi della finestra sono presenti in ordine crescente, anche se vuoti.
     *
     * @param list<Vehicle> $vehicles
     * @return DashboardCharts
     */
    private function computeForVehicles(array $vehicles, int $months): array
    {
        if ($months < 1 || $months > self::MAX_MONTHS) {
            throw new \InvalidArgumentException(sprintf('months must be between 1 and %d, got %d', self::MAX_MONTHS, $months));
        }

        $currentMonth = $this->clock->today()->modify('first day of this month');
        $firstMonth = $currentMonth->modify(sprintf('-%d months', $months - 1));
        $from = $firstMonth->format('Y-m-d');
        $until = $currentMonth->modify('+1 month')->format('Y-m-d'); // escluso

        $monthKeys = [];
        for ($month = $firstMonth; $month <= $currentMonth; $month = $month->modify('+1 month')) {
            $monthKeys[] = $month->format('Y-m');
        }

        $fuelTypes = self::fuelTypes($vehicles);

        // Uscita anticipata: senza veicoli non c'è niente da aggregare.
        $ids = self::ids($vehicles);
        $spending = $ids === [] ? [] : $this->monthlySpendingCents($ids, $from, $until);
        $expenseCategories = $ids === [] ? [] : $this->expenseCategoryCents($ids, $from, $until);
        $kmDriven = $ids === [] ? [] : $this->monthlyKmDriven($vehicles, $monthKeys, $from, $until);
        $consumption = $ids === [] ? [] : $this->monthlyConsumption($vehicles, $from, $until);

        $points = [];
        $totalCents = 0;
        $totalKm = 0;
        $sourceCents = ['refuelings' => 0, 'maintenances' => 0, 'expenses' => 0];
        foreach ($monthKeys as $key) {
            $cents = $spending[$key] ?? [];
            $refuelings = $cents['refuelings'] ?? 0;
            $maintenances = $cents['maintenances'] ?? 0;
            $expenses = $cents['expenses'] ?? 0;
            $monthTotal = $refuelings + $maintenances + $expenses;
            $km = $kmDriven[$key] ?? 0;

            $sourceCents['refuelings'] += $refuelings;
            $sourceCents['maintenances'] += $maintenances;
            $sourceCents['expenses'] += $expenses;
            $totalCents += $monthTotal;
            $totalKm += $km;

            $monthConsumption = [];
            foreach ($fuelTypes as $fuel) {
                $monthConsumption[$fuel] = $this->consumption->kmPerLiter($consumption[$key][$fuel] ?? []);
            }

            $points[] = [
                'month' => $key,
                'spending' => [
                    'refuelings' => self::money($refuelings),
                    'maintenances' => self::money($maintenances),
                    'expenses' => self::money($expenses),
                    'total' => self::money($monthTotal),
                ],
                'kmDriven' => $km,
                'consumption' => $monthConsumption,
            ];
        }

        return [
            'from' => $firstMonth->format('Y-m'),
            'to' => $currentMonth->format('Y-m'),
            'fuelTypes' => $fuelTypes,
            'months' => $points,
            'spendingByCategory' => self::byCategory([
                self::CATEGORY_FUEL => $sourceCents['refuelings'],
                self::CATEGORY_MAINTENANCE => $sourceCents['maintenances'],
            ] + $expenseCategories),
            'totals' => ['spending' => self::money($totalCents), 'kmDriven' => $totalKm],
        ];
    }

    // ----- query -----

    /**
     * Spese della finestra per mese e per sorgente, in centesimi.
     *
     * @param list<int> $ids
     * @return array<string, array<'refuelings'|'maintenances'|'expenses', int>> mese (Y-m) → sorgente → centesimi
     */
    private function monthlySpendingCents(array $ids, string $from, string $until): array
    {
        /** @var list<array{source: 'refuelings'|'maintenances'|'expenses', ym: string, amount: string|null}> $rows */
        $rows = $this->db->fetchAllAssociative(
            "SELECT 'refuelings' AS source, DATE_FORMAT(refueled_at, '%Y-%m') AS ym, SUM(total_cost) AS amount
             FROM refuelings
             WHERE vehicle_id IN (:ids) AND refueled_at >= :from AND refueled_at < :until
             GROUP BY ym
             UNION ALL
             SELECT 'maintenances', DATE_FORMAT(performed_at, '%Y-%m') AS ym, SUM(cost)
             FROM maintenances
             WHERE vehicle_id IN (:ids) AND performed_at >= :from AND performed_at < :until AND cost IS NOT NULL
             GROUP BY ym
             UNION ALL
             SELECT 'expenses', DATE_FORMAT(occurred_at, '%Y-%m') AS ym, SUM(amount)
             FROM expenses
             WHERE vehicle_id IN (:ids) AND occurred_at >= :from AND occurred_at < :until
             GROUP BY ym",
            ['ids' => $ids, 'from' => $from, 'until' => $until],
            ['ids' => ArrayParameterType::INTEGER],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['ym']][$row['source']] = self::cents($row['amount']);
        }

        return $result;
    }

    /**
     * Spese della finestra per categoria (valori di ExpenseCategory), in centesimi.
     *
     * @param list<int> $ids
     * @return array<string, int>
     */
    private function expenseCategoryCents(array $ids, string $from, string $until): array
    {
        /** @var list<array{category: string, amount: string|null}> $rows */
        $rows = $this->db->fetchAllAssociative(
            'SELECT category, SUM(amount) AS amount
             FROM expenses
             WHERE vehicle_id IN (:ids) AND occurred_at >= :from AND occurred_at < :until
             GROUP BY category',
            ['ids' => $ids, 'from' => $from, 'until' => $until],
            ['ids' => ArrayParameterType::INTEGER],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['category']] = self::cents($row['amount']);
        }

        return $result;
    }

    /**
     * Km percorsi per mese, sommati tra i veicoli. Per ogni veicolo l'odometro "cumulato" di un
     * mese è il massimo tra quello del mese prima e la lettura più alta del mese (rifornimenti e
     * manutenzioni); la base è il massimo tra i km iniziali e le letture precedenti alla finestra.
     * Un mese senza letture vale 0 e il primo mese con una lettura assorbe la differenza; una
     * lettura più bassa di una precedente (refuso) non produce mai valori negativi.
     *
     * @param list<Vehicle> $vehicles
     * @param list<string> $monthKeys
     * @return array<string, int> mese (Y-m) → km
     */
    private function monthlyKmDriven(array $vehicles, array $monthKeys, string $from, string $until): array
    {
        /** @var list<array{vehicle_id: string|int, ym: string, km: string|int}> $rows */
        $rows = $this->db->fetchAllAssociative(
            "SELECT vehicle_id, ym, MAX(km) AS km FROM (
                SELECT vehicle_id, km,
                       CASE WHEN refueled_at < :from THEN :before ELSE DATE_FORMAT(refueled_at, '%Y-%m') END AS ym
                FROM refuelings
                WHERE vehicle_id IN (:ids) AND refueled_at < :until
                UNION ALL
                SELECT vehicle_id, km,
                       CASE WHEN performed_at < :from THEN :before ELSE DATE_FORMAT(performed_at, '%Y-%m') END
                FROM maintenances
                WHERE vehicle_id IN (:ids) AND performed_at < :until
             ) readings
             GROUP BY vehicle_id, ym",
            ['ids' => self::ids($vehicles), 'from' => $from, 'until' => $until, 'before' => self::BEFORE_WINDOW],
            ['ids' => ArrayParameterType::INTEGER],
        );

        /** @var array<int, array<string, int>> $readings veicolo → mese (o BEFORE_WINDOW) → km massimo */
        $readings = [];
        foreach ($rows as $row) {
            $readings[(int) $row['vehicle_id']][$row['ym']] = (int) $row['km'];
        }

        $result = array_fill_keys($monthKeys, 0);
        foreach ($vehicles as $vehicle) {
            $own = $readings[(int) $vehicle->getId()] ?? [];
            $cumulative = max($vehicle->getInitialKm(), $own[self::BEFORE_WINDOW] ?? 0);
            foreach ($monthKeys as $key) {
                $next = max($cumulative, $own[$key] ?? 0);
                $result[$key] += $next - $cumulative;
                $cumulative = $next;
            }
        }

        return $result;
    }

    /**
     * Intervalli fill-to-fill chiusi nella finestra, per mese del pieno di chiusura e carburante.
     * Gli intervalli si calcolano per singolo veicolo su tutta la sua storia (un pieno precedente
     * alla finestra resta un'ancora valida), mai mescolando rifornimenti di veicoli diversi.
     *
     * @param list<Vehicle> $vehicles
     * @return array<string, array<string, list<FillInterval>>> mese (Y-m) → carburante → intervalli
     */
    private function monthlyConsumption(array $vehicles, string $from, string $until): array
    {
        /** @var list<array{vehicle_id: string|int, km: string|int, liters: string, full_tank: string|int|bool, fuel_type: string, refueled_at: string}> $rows */
        $rows = $this->db->fetchAllAssociative(
            'SELECT vehicle_id, km, liters, full_tank, fuel_type, refueled_at
             FROM refuelings
             WHERE vehicle_id IN (:ids) AND refueled_at < :until
             ORDER BY vehicle_id ASC, refueled_at ASC, km ASC, id ASC',
            ['ids' => self::ids($vehicles), 'until' => $until],
            ['ids' => ArrayParameterType::INTEGER],
        );

        /** @var array<int, list<RefuelingRow>> $byVehicle */
        $byVehicle = [];
        foreach ($rows as $row) {
            $byVehicle[(int) $row['vehicle_id']][] = $row;
        }

        $result = [];
        foreach ($vehicles as $vehicle) {
            $own = $byVehicle[(int) $vehicle->getId()] ?? [];
            foreach ($vehicle->getAllFuelTypes() as $fuel) {
                foreach ($this->consumption->intervals($own, $fuel->value) as $interval) {
                    if ($interval['closedOn'] < $from) {
                        continue; // chiuso prima della finestra: serviva solo come ancora
                    }
                    $result[substr($interval['closedOn'], 0, 7)][$fuel->value][] = $interval;
                }
            }
        }

        return $result;
    }

    // ----- helper puri -----

    /**
     * @param list<Vehicle> $vehicles
     * @return list<int>
     */
    private static function ids(array $vehicles): array
    {
        return array_map(static fn (Vehicle $v): int => (int) $v->getId(), $vehicles);
    }

    /**
     * Carburanti dei veicoli, deduplicati: per veicolo, primario prima del secondario.
     *
     * @param list<Vehicle> $vehicles
     * @return list<string>
     */
    private static function fuelTypes(array $vehicles): array
    {
        $fuels = [];
        foreach ($vehicles as $vehicle) {
            foreach ($vehicle->getAllFuelTypes() as $fuel) {
                $fuels[$fuel->value] = true;
            }
        }

        return array_keys($fuels);
    }

    /**
     * Voci a zero omesse, ordinate per importo decrescente e a parità per chiave.
     *
     * @param array<string, int> $cents
     * @return list<array{category: string, amount: string}>
     */
    private static function byCategory(array $cents): array
    {
        $cents = array_filter($cents, static fn (int $amount): bool => $amount !== 0);
        uksort($cents, static fn (string $a, string $b): int => [$cents[$b], $a] <=> [$cents[$a], $b]);

        $result = [];
        foreach ($cents as $category => $amount) {
            $result[] = ['category' => $category, 'amount' => self::money($amount)];
        }

        return $result;
    }

    private static function cents(?string $decimal): int
    {
        return (int) round((float) $decimal * 100);
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
