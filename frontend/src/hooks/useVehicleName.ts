import { useVehicles } from '@/hooks/useVehicles';

/**
 * Nome del veicolo selezionato (`vehicleId`), o undefined se non trovato/in caricamento.
 * Riusa la query `useVehicles` (cache condivisa con VehicleScopedList → nessun fetch extra).
 */
export function useVehicleName(vehicleId: number): string | undefined {
  const { data } = useVehicles();
  return data?.find((v) => v.id === vehicleId)?.name;
}
