import { z } from 'zod';

/**
 * Config Web Push istanza. Match backend PushSettingsRequest — `enabled` non è
 * più nel form: generare una coppia VAPID attiva già da sola (vedi
 * PushSettingsController::generate), il form gestisce solo il subject.
 */
export const pushSettingsSchema = z.object({
  subject: z.string().max(255).optional(),
});
export type PushSettingsFormData = z.infer<typeof pushSettingsSchema>;
