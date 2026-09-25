import { z } from 'zod';
import { FUEL_TYPES, VEHICLE_TYPES } from '@/api/types/vehicle';

/**
 * Vehicle Zod schema — single source of truth per form + validation.
 * Match con backend `App\Dto\Request\VehicleRequest`.
 */
export const vehicleSchema = z
  .object({
    name: z.string().min(1, 'vehicle.name.required').max(150),
    brand: z.string().min(1, 'vehicle.brand.required').max(80),
    model: z.string().min(1, 'vehicle.model.required').max(80),
    year: z
      .number({ message: 'vehicle.year.required' })
      .int()
      .min(1900, 'vehicle.year.out_of_range')
      .max(new Date().getFullYear() + 1, 'vehicle.year.out_of_range'),
    type: z.enum(VEHICLE_TYPES, { message: 'vehicle.type.required' }),
    fuelType: z.enum(FUEL_TYPES, { message: 'vehicle.fuel_type.required' }),
    secondaryFuelType: z.enum(FUEL_TYPES).nullable().optional(),
    licensePlate: z.string().max(20).nullable().optional(),
    vin: z.string().max(50).nullable().optional(),
    initialKm: z.number({ message: 'vehicle.initial_km.required' }).int().nonnegative(),
    notes: z.string().max(2000).nullable().optional(),
  })
  .refine((data) => !data.secondaryFuelType || data.secondaryFuelType !== data.fuelType, {
    message: 'vehicle.duplicate_fuel_type',
    path: ['secondaryFuelType'],
  });

export type VehicleFormData = z.infer<typeof vehicleSchema>;
