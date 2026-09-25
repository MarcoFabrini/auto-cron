export const MAINTENANCE_TYPES = [
  'oil_change',
  'filters',
  'brakes',
  'tires',
  'inspection',
  'repair',
  'battery',
  'belt_change',
  'spark_plugs',
  'coolant',
  'clutch',
  'suspension',
  'body_work',
  'wash',
  'other',
] as const;
export type MaintenanceType = (typeof MAINTENANCE_TYPES)[number];

export const MAINTENANCE_CATEGORIES = ['scheduled', 'unscheduled'] as const;
export type MaintenanceCategory = (typeof MAINTENANCE_CATEGORIES)[number];

/**
 * Raw backend response — match Maintenance entity con groups serializzati.
 * Lista usa groups più snelli (no workshop), detail usa quelli completi.
 */
export interface MaintenanceResponse {
  id: number;
  vehicle: { id: number; name: string; brand: string; model: string };
  performedAt: string;        // ISO datetime "2026-05-15T00:00:00+00:00"
  km: number;
  type: MaintenanceType;
  category: MaintenanceCategory;
  description: string;
  cost?: string | null;
  workshop?: string | null;
}

/** Normalized client-side shape — vehicle nested → vehicleId flat, date ISO sliced. */
export interface Maintenance {
  id: number;
  vehicleId: number;
  performedAt: string;        // YYYY-MM-DD (slice 0,10)
  km: number;
  type: MaintenanceType;
  category: MaintenanceCategory;
  description: string;
  cost: string | null;
  workshop: string | null;
}

export interface CreateMaintenanceDto {
  vehicleId: number;
  performedAt: string;        // YYYY-MM-DD
  km: number;
  type: MaintenanceType;
  category: MaintenanceCategory;
  description: string;
  cost?: string | null;
  workshop?: string | null;
}

export type UpdateMaintenanceDto = CreateMaintenanceDto;

/** Normalize backend response a client shape. */
export function adaptMaintenance(raw: MaintenanceResponse): Maintenance {
  return {
    id: raw.id,
    vehicleId: raw.vehicle.id,
    performedAt: raw.performedAt.slice(0, 10),
    km: raw.km,
    type: raw.type,
    category: raw.category,
    description: raw.description,
    cost: raw.cost ?? null,
    workshop: raw.workshop ?? null,
  };
}
