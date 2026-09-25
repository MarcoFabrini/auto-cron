/**
 * Vehicle types — manuali per ora. In futuro autogen via
 * `npm run api:types` (openapi-typescript da /api/doc.json).
 */

export const VEHICLE_TYPES = [
  'car',
  'motorcycle',
  'van',
  'camper',
  'boat',
  'truck',
  'bicycle',
  'scooter',
  'trailer',
  'tractor',
] as const;
export type VehicleType = (typeof VEHICLE_TYPES)[number];

export const FUEL_TYPES = [
  'gasoline',
  'diesel',
  'lpg',
  'cng',
  'electric',
  'hybrid',
  'hydrogen',
  'ethanol',
  'biodiesel',
] as const;
export type FuelType = (typeof FUEL_TYPES)[number];

export interface Vehicle {
  id: number;
  name: string;
  brand: string;
  model: string;
  year: number;
  licensePlate: string | null;
  vin: string | null;
  type: VehicleType;
  fuelType: FuelType;
  secondaryFuelType: FuelType | null;
  initialKm: number;
  notes: string | null;
  /** Assente nel group vehicle:list (undefined); presente in vehicle:read. */
  archivedAt?: string | null;
  photoPath?: string | null;
  /** Solo nel dettaglio (GET /api/vehicles/{id}): permessi dell'utente corrente. */
  permissions?: VehiclePermissions;
}

export interface VehiclePermissions {
  canEdit: boolean;
  canDelete: boolean;
  canShare: boolean;
}

export interface CreateVehicleDto {
  name: string;
  brand: string;
  model: string;
  year: number;
  type: VehicleType;
  fuelType: FuelType;
  secondaryFuelType?: FuelType | null;
  licensePlate?: string | null;
  vin?: string | null;
  initialKm: number;
  notes?: string | null;
}

export type UpdateVehicleDto = CreateVehicleDto;
