import type { Vehicle } from '@/api/types/vehicle';

/**
 * Se mostrare i grafici nel dettaglio di un veicolo. Scelta solo di presentazione (l'accesso lo
 * decide il backend): si nascondono quando duplicherebbero la dashboard, cioè se il veicolo è
 * dell'utente, non è archiviato ed è il suo unico veicolo proprio attivo. Veicoli condivisi, di
 * altri membri o archiviati mostrano sempre i loro grafici, che in dashboard non ci sono.
 *
 * `visibleVehicles` è la lista di GET /api/vehicles (esclude gli archiviati); il veicolo corrente
 * si esclude per id, così una lista non aggiornata non lo conta due volte.
 */
export function shouldShowVehicleCharts(
  vehicle: Pick<Vehicle, 'id' | 'ownership' | 'archivedAt'>,
  visibleVehicles: Pick<Vehicle, 'id' | 'ownership'>[],
): boolean {
  if (vehicle.ownership !== 'owned' || vehicle.archivedAt) return true;

  return visibleVehicles.some((v) => v.id !== vehicle.id && v.ownership === 'owned');
}
