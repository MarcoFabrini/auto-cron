import { authFetch } from '@/api/client';
import type { CreateVehicleDto, UpdateVehicleDto, Vehicle } from '@/api/types/vehicle';
import type { VehicleStats } from '@/api/types/vehicleStats';

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

export function archiveVehicle(id: number) {
  return authFetch<Vehicle>(`/api/vehicles/${id}/archive`, { method: 'POST' });
}

export function getVehicleStats(id: number) {
  return authFetch<VehicleStats>(`/api/vehicles/${id}/stats`);
}
