import { authFetch } from '@/api/client';
import type { CreateVehicleDto, UpdateVehicleDto, Vehicle } from '@/api/types/vehicle';
import type { VehicleStats } from '@/api/types/vehicleStats';
import { dashboardChartsSchema, type DashboardCharts } from '@/api/types/dashboardCharts';

/**
 * Vehicle API endpoints — thin wrapper su authFetch.
 * Restituisce dati typed senza side-effect.
 */

export function listVehicles() {
  return authFetch<Vehicle[]>('/api/vehicles');
}

export function getVehicle(id: number) {
  return authFetch<Vehicle>(`/api/vehicles/${id}`);
}

export function createVehicle(body: CreateVehicleDto) {
  return authFetch<Vehicle>('/api/vehicles', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

export function updateVehicle(id: number, body: UpdateVehicleDto) {
  return authFetch<Vehicle>(`/api/vehicles/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

export function deleteVehicle(id: number) {
  return authFetch<void>(`/api/vehicles/${id}`, { method: 'DELETE' });
}

export function unarchiveVehicle(id: number) {
  return authFetch<Vehicle>(`/api/vehicles/${id}/unarchive`, { method: 'POST' });
}

export function archiveVehicle(id: number) {
  return authFetch<Vehicle>(`/api/vehicles/${id}/archive`, { method: 'POST' });
}

export function getVehicleStats(id: number) {
  return authFetch<VehicleStats>(`/api/vehicles/${id}/stats`);
}

/** Grafici del solo veicolo degli ultimi `months` mesi: stesso contratto della dashboard. */
export async function getVehicleCharts(id: number, months: number): Promise<DashboardCharts> {
  const raw = await authFetch<unknown>(`/api/vehicles/${id}/charts?months=${months}`);
  return dashboardChartsSchema.parse(raw);
}
