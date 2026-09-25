import { z } from 'zod';
import { REMINDER_TYPES } from '@/api/types/reminder';

export const reminderSchema = z
  .object({
    vehicleId: z.number({ message: 'reminder.vehicle.required' }).int().positive(),
    type: z.enum(REMINDER_TYPES, { message: 'reminder.type.required' }),
    description: z.string().min(1, 'reminder.description.required').max(2000),
    dueDate: z
      .string()
      .regex(/^\d{4}-\d{2}-\d{2}$/, 'common.invalid_date')
      .nullable()
      .optional(),
    dueKm: z.number().int().nonnegative().nullable().optional(),
    notifyDaysBefore: z.number({ message: 'reminder.notify_days.required' }).int().min(0).max(365),
  })
  .refine((d) => !!d.dueDate || (d.dueKm != null && d.dueKm > 0), {
    message: 'reminder.due_required',
    path: ['dueDate'],
  });

export type ReminderFormData = z.infer<typeof reminderSchema>;
