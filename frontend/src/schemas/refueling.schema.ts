import { z } from 'zod';
import { FUEL_TYPES } from '@/api/types/vehicle';
import { decimalString } from './decimal';

export const refuelingSchema = z.object({
  vehicleId: z.number({ message: 'refueling.vehicle.required' }).int().positive(),
  refueledAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'common.invalid_date'),
  km: z.number({ message: 'refueling.km.required' }).int().nonnegative(),
  liters: decimalString(3),
  pricePerLiter: decimalString(3),
  fuelType: z.enum(FUEL_TYPES, { message: 'refueling.fuel_type.required' }),
  fullTank: z.boolean(),
  station: z.string().max(150).nullable().optional(),
  notes: z.string().max(2000).nullable().optional(),
});

export type RefuelingFormData = z.infer<typeof refuelingSchema>;
