import { useQueries } from '@tanstack/react-query';
import { getVehicleStats } from '@/api/endpoints/vehicles';
import { vehicleStatsKey, useVehicles } from '@/hooks/useVehicles';
import type { Vehicle } from '@/api/types/vehicle';

export interface DashboardData {
  /** Solo i veicoli propri: la base di totali e grafici. */
  vehicles: Vehicle[];
  /** Veicoli visibili ma non propri (condivisi, o di altri membri per owner/admin): esclusi. */
  excludedCount: number;
  totalCost: number;
  totalRefuelings: number;
  totalMaintenances: number;
  totalExpenses: number;
  isLoading: boolean;
  /** Errore bloccante (lista veicoli): senza veicoli non c'è nulla da mostrare. */
  error: unknown;
  /** Le statistiche di alcuni veicoli non si sono caricate: i totali sono parziali. */
  partial: boolean;
}

/**
 * useDashboard — aggrega le statistiche dei veicoli PROPRI dell'utente.
 * Fetch lista veicoli, poi stats per veicolo in parallelo (useQueries),
 * somma i totali. Adatto a flotte personali (pochi veicoli).
 *
 * Fonte unica per totali e grafici della dashboard: i veicoli condivisi in sola lettura e,
 * per owner/admin dell'org, quelli degli altri membri si vedono nella lista ma qui non entrano.
 */
export function useDashboard(): DashboardData {
  const vehiclesQuery = useVehicles();
  const visible = vehiclesQuery.data ?? [];
  const vehicles = visible.filter((v) => v.ownership === 'owned');

  const statsQueries = useQueries({
    queries: vehicles.map((v) => ({
      queryKey: vehicleStatsKey(v.id),
      queryFn: () => getVehicleStats(v.id),
      staleTime: 60_000,
    })),
  });

  const statsLoading = statsQueries.some((q) => q.isLoading);
  const partial = statsQueries.some((q) => q.isError);

  let totalCost = 0;
  let totalRefuelings = 0;
  let totalMaintenances = 0;
  let totalExpenses = 0;

  for (const q of statsQueries) {
    if (!q.data) continue;
    totalCost += Number.parseFloat(q.data.totals.cost) || 0;
    totalRefuelings += q.data.totals.refuelings;
    totalMaintenances += q.data.totals.maintenances;
    totalExpenses += q.data.totals.expenses;
  }

  return {
    vehicles,
    excludedCount: visible.length - vehicles.length,
    totalCost,
    totalRefuelings,
    totalMaintenances,
    totalExpenses,
    isLoading: vehiclesQuery.isLoading || statsLoading,
    error: vehiclesQuery.error,
    partial,
  };
}
