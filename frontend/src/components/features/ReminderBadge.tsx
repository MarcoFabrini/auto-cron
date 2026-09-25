import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui';
import { reminderUrgency, type Reminder } from '@/api/types/reminder';
import { useVehicleStats } from '@/hooks/useVehicles';

/**
 * ReminderBadge feature — pill stato urgenza reminder.
 * overdue=destructive, soon=warning, ok=secondary, done=success.
 * Per i promemoria a km legge il chilometraggio attuale del veicolo (stats, in cache
 * e deduplicato per veicolo): senza, un promemoria a km non sarebbe mai urgente.
 */
export interface ReminderBadgeProps {
  reminder: Reminder;
}

const variantByUrgency = {
  overdue: 'destructive',
  soon: 'warning',
  ok: 'secondary',
  done: 'success',
} as const;

export function ReminderBadge({ reminder }: ReminderBadgeProps) {
  const { t } = useTranslation();
  const needsKm = reminder.dueKm != null && !reminder.completedAt;
  const stats = useVehicleStats(reminder.vehicleId, { enabled: needsKm });
  const urgency = reminderUrgency(reminder, { currentKm: stats.data?.currentKm });
  return <Badge variant={variantByUrgency[urgency]}>{t(`reminder.urgency.${urgency}`)}</Badge>;
}
