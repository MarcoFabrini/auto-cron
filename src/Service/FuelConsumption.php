<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Algoritmo fill-to-fill del consumo, senza I/O: lo usano sia le statistiche del singolo veicolo
 * ({@see VehicleStatsService}) sia i grafici della dashboard ({@see DashboardChartsService}).
 *
 * Tra ogni coppia di rifornimenti full_tank consecutivi sullo stesso carburante, il consumo è
 * (km2 - km1) / (litri di TUTTI i rifornimenti, pieni e parziali, effettuati nell'intervallo,
 * esclusi quelli del pieno di partenza). Un pieno riporta il serbatoio allo stesso livello,
 * quindi la somma dei litri immessi tra due pieni corrisponde esattamente al consumo, a
 * prescindere da quanti rifornimenti parziali ci sono nel mezzo.
 *
 * Un pieno il cui odometro non supera quello dell'ultimo pieno valido (refuso) conta come parziale:
 * accumula litri verso il pieno valido successivo e non diventa mai il punto di partenza.
 *
 * Allo stesso modo un pieno che darebbe più di {@see self::MAX_PLAUSIBLE_KM_PER_LITER} km/l (refuso verso l'alto,
 * es. 100600 invece di 10600) non conta come intervallo e non diventa il punto di partenza: si comporta da
 * parziale, i suoi litri si sommano verso il prossimo pieno plausibile e i pieni corretti successivi restano validi.
 *
 * Veicoli bi-fuel: se tra i due pieni c'è un rifornimento dell'ALTRO carburante, i km percorsi
 * nell'intervallo sono in parte fatti con quello: attribuirli tutti al primo ne sovrastimerebbe
 * il consumo. Quell'intervallo viene scartato (il pieno successivo fa da nuovo punto di partenza).
 *
 * @phpstan-type RefuelingRow array{km: string|int, liters: string, full_tank: string|int|bool, fuel_type: string, refueled_at: string}
 * @phpstan-type FillInterval array{closedOn: string, km: int, liters: float}
 */
final class FuelConsumption
{
    /**
     * Soglia di plausibilità: un intervallo oltre questi km per litro è un dato sbagliato (tipicamente un
     * refuso sull'odometro verso l'alto), non un consumo reale. Per i veicoli elettrici i kWh sono
     * registrati come litri, quindi 100 è comunque un margine ampio.
     */
    public const MAX_PLAUSIBLE_KM_PER_LITER = 100.0;

    /**
     * Intervalli validi tra pieni consecutivi di `$fuel`, nell'ordine in cui si chiudono.
     *
     * @param list<RefuelingRow> $rows tutti i rifornimenti di UN solo veicolo (anche dell'altro
     *                                 carburante), ordinati per data, km, id
     * @return list<FillInterval> `closedOn` = data (Y-m-d) del pieno che chiude l'intervallo
     */
    public function intervals(array $rows, string $fuel): array
    {
        $intervals = [];

        $lastFullKm = null;
        $litersSinceLastFull = 0.0;
        $mixedWithOtherFuel = false;

        foreach ($rows as $row) {
            if ($row['fuel_type'] !== $fuel) {
                // Rifornimento dell'altro carburante: l'intervallo in corso non è più "puro".
                $mixedWithOtherFuel = true;
                continue;
            }

            if ($lastFullKm !== null) {
                $litersSinceLastFull += (float) $row['liters'];
            }

            if (!self::isFullTank($row['full_tank'])) {
                continue;
            }

            // Un pieno con km non superiori all'ultimo punto di partenza (refuso sull'odometro o riga
            // inserita fuori ordine) non è un punto di partenza affidabile: si tratta come un rifornimento
            // parziale. I suoi litri restano nel totale (già sommati sopra) e contano per il prossimo pieno
            // valido; se diventasse l'ancora, il refuso falserebbe anche l'intervallo successivo.
            if ($lastFullKm !== null && (int) $row['km'] <= $lastFullKm) {
                continue;
            }

            // Intervallo implausibile (più di MAX_PLAUSIBLE_KM_PER_LITER km/l): stesso trattamento di un parziale.
            // Vale solo per intervalli "puri": con l'altro carburante in mezzo i km non sono attribuibili ai soli litri
            // di questo carburante, quindi il rapporto non è confrontabile e l'intervallo è comunque scartato.
            if (
                $lastFullKm !== null
                && !$mixedWithOtherFuel
                && $litersSinceLastFull > 0
                && ((int) $row['km'] - $lastFullKm) / $litersSinceLastFull > self::MAX_PLAUSIBLE_KM_PER_LITER
            ) {
                continue;
            }

            if ($lastFullKm !== null && !$mixedWithOtherFuel) {
                $deltaKm = (int) $row['km'] - $lastFullKm;
                if ($litersSinceLastFull > 0) {
                    $intervals[] = [
                        'closedOn' => substr($row['refueled_at'], 0, 10),
                        'km' => $deltaKm,
                        'liters' => $litersSinceLastFull,
                    ];
                }
            }

            $lastFullKm = (int) $row['km'];
            $litersSinceLastFull = 0.0;
            $mixedWithOtherFuel = false;
        }

        return $intervals;
    }

    /**
     * Media km/l su un insieme di intervalli: Σkm / Σlitri (non la media delle medie), a 2 decimali.
     *
     * @param list<FillInterval> $intervals
     */
    public function kmPerLiter(array $intervals): ?float
    {
        $km = 0;
        $liters = 0.0;
        foreach ($intervals as $interval) {
            $km += $interval['km'];
            $liters += $interval['liters'];
        }

        return $liters > 0 ? round($km / $liters, 2) : null;
    }

    private static function isFullTank(string|int|bool $value): bool
    {
        return (bool) (is_string($value) ? (int) $value : $value);
    }
}
