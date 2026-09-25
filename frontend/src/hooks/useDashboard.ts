import { useQueries } from '@tanstack/react-query';
import { getVehicleStats } from '@/api/endpoints/vehicles';
import { vehicleStatsKey, useVehicles } from '@/hooks/useVehicles';
import type { Vehicle } from '@/api/types/vehicle';

export interface DashboardData {
  vehicles: Vehicle[];
  totalCost: number;
  totalRefuelings: number;
  totalMaintenances: number;
  totalExpenses: number;
  isLoading: boolean;
  error: unknown;
}

/**
 * useDashboard — aggrega le statistiche di tutti i veicoli.
 * Fetch lista veicoli, poi stats per veicolo in parallelo (useQueries),
 * somma i totali. Adatto a flotte personali (pochi veicoli).
 */
export function useDashboard(): DashboardData {
  const vehiclesQuery = useVehicles();
  const vehicles = vehiclesQuery.data ?? [];

  const statsQueries = useQueries({
    queries: vehicles.map((v) => ({
      queryKey: vehicleStatsKey(v.id),
      queryFn: () => getVehicleStats(v.id),
      staleTime: 60_000,
    })),
  });

  const statsLoading = statsQueries.some((q) => q.isLoading);
  const statsError = statsQueries.find((q) => q.error)?.error;

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
    totalCost,
    totalRefuelings,
    totalMaintenances,
    totalExpenses,
    isLoading: vehiclesQuery.isLoading || statsLoading,
    error: vehiclesQuery.error ?? statsError,
  };
}
