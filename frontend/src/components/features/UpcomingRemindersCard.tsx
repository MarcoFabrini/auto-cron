import { useTranslation } from 'react-i18next';
import { Bell } from 'lucide-react';
import { Alert, Card, CardContent, CardHeader, CardTitle, Skeleton, Text } from '@/components/ui';
import { ReminderCard } from './ReminderCard';
import { useUpcomingReminders } from '@/hooks/useReminders';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';

/**
 * Card dashboard con le prossime scadenze (a data) su tutti i veicoli dell'organizzazione.
 * Le scadenze a km sono escluse: richiederebbero il chilometraggio attuale di ogni veicolo,
 * vedi il commento su `ReminderRepository::findUpcomingForOrganization`.
 */
export function UpcomingRemindersCard() {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useUpcomingReminders();

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('dashboard.upcoming_reminders')}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {error && <Alert variant="error">{errorMessage(error)}</Alert>}

        {isLoading && (
          <>
            <Skeleton className="h-20 w-full rounded-lg" />
            <Skeleton className="h-20 w-full rounded-lg" />
          </>
        )}

        {data && data.length === 0 && (
          <div className="flex items-center gap-3 py-2 text-muted-foreground">
            <Bell className="size-5 shrink-0" />
            <Text variant="muted">{t('dashboard.upcoming_reminders_empty')}</Text>
          </div>
        )}

        {data && data.length > 0 && (
          <div className="space-y-3">
            {data.map((r) => (
              <ReminderCard key={r.id} reminder={r} showVehicleName />
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
