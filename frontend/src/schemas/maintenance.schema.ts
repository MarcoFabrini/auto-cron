import { z } from 'zod';
import { MAINTENANCE_CATEGORIES, MAINTENANCE_TYPES } from '@/api/types/maintenance';
import { decimalString } from './decimal';

const dateString = z
  .string()
  .regex(/^\d{4}-\d{2}-\d{2}$/, 'common.invalid_date');

export const maintenanceSchema = z.object({
  vehicleId: z.number({ message: 'maintenance.vehicle.required' }).int().positive(),
  performedAt: dateString,
  km: z.number({ message: 'maintenance.km.required' }).int().nonnegative(),
  type: z.enum(MAINTENANCE_TYPES, { message: 'maintenance.type.required' }),
  category: z.enum(MAINTENANCE_CATEGORIES, { message: 'maintenance.category.required' }),
  description: z.string().min(1, 'maintenance.description.required').max(2000),
  cost: decimalString(2).nullable().optional(),
  workshop: z.string().max(150).nullable().optional(),
});

export type MaintenanceFormData = z.infer<typeof maintenanceSchema>;
