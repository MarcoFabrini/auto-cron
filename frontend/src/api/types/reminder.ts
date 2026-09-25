import { daysUntil } from '@/lib/format';

export const REMINDER_TYPES = [
  'inspection',
  'insurance',
  'road_tax',
  'service',
  'oil_change',
  'tires',
  'battery',
  'license',
  'extinguisher',
  'custom',
] as const;
export type ReminderType = (typeof REMINDER_TYPES)[number];

export interface ReminderResponse {
  id: number;
  vehicle: { id: number; name: string; brand: string; model: string };
  type: ReminderType;
  description: string;
  dueDate: string | null;       // ISO datetime
  dueKm: number | null;
  notifyDaysBefore: number;
  completedAt: string | null;
}

export interface Reminder {
  id: number;
  vehicleId: number;
  type: ReminderType;
  description: string;
  dueDate: string | null;       // YYYY-MM-DD
  dueKm: number | null;
  notifyDaysBefore: number;
  completedAt: string | null;   // ISO datetime
}

export interface CreateReminderDto {
  vehicleId: number;
  type: ReminderType;
  description: string;
  dueDate?: string | null;
  dueKm?: number | null;
  notifyDaysBefore: number;
}

export type UpdateReminderDto = CreateReminderDto;

export function adaptReminder(raw: ReminderResponse): Reminder {
  return {
    id: raw.id,
    vehicleId: raw.vehicle.id,
    type: raw.type,
    description: raw.description,
    dueDate: raw.dueDate ? raw.dueDate.slice(0, 10) : null,
    dueKm: raw.dueKm,
    notifyDaysBefore: raw.notifyDaysBefore,
    completedAt: raw.completedAt,
  };
}

export type ReminderUrgency = 'overdue' | 'soon' | 'ok' | 'done';

/**
 * Sotto questa distanza (km) dalla soglia un promemoria a chilometri è "in scadenza".
 * Stesso valore e stesse regole del backend (`Reminder::KM_SOON_THRESHOLD` / `Reminder::urgency()`),
 * che è ciò che decide quando parte la notifica: tenerli allineati.
 */
export const REMINDER_KM_SOON = 1000;

const URGENCY_RANK = { ok: 0, soon: 1, overdue: 2 } as const;

function dateUrgency(r: Reminder, now: Date): keyof typeof URGENCY_RANK {
  if (!r.dueDate) return 'ok';
  const daysLeft = daysUntil(r.dueDate, now);
  if (daysLeft === null) return 'ok';
  // Scade oggi (daysLeft = 0) = ancora in tempo, "in scadenza" ma non scaduto.
  if (daysLeft < 0) return 'overdue';
  return daysLeft <= r.notifyDaysBefore ? 'soon' : 'ok';
}

function kmUrgency(r: Reminder, currentKm: number | null | undefined): keyof typeof URGENCY_RANK {
  if (r.dueKm == null || currentKm == null) return 'ok';
  const kmLeft = r.dueKm - currentKm;
  if (kmLeft < 0) return 'overdue';
  return kmLeft <= REMINDER_KM_SOON ? 'soon' : 'ok';
}

/**
 * Urgenza di un promemoria non completato: la peggiore tra scadenza a data e a chilometri.
 * Per la parte a km serve il chilometraggio attuale del veicolo (`currentKm`); senza, conta
 * solo la data. Confronto a giorni di calendario locali, non a istanti.
 */
export function reminderUrgency(
  r: Reminder,
  opts: { currentKm?: number | null; now?: Date } = {},
): ReminderUrgency {
  if (r.completedAt) return 'done';
  const byDate = dateUrgency(r, opts.now ?? new Date());
  const byKm = kmUrgency(r, opts.currentKm);
  return URGENCY_RANK[byDate] >= URGENCY_RANK[byKm] ? byDate : byKm;
}
