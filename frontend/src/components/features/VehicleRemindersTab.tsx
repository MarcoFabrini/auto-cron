import { useTranslation } from 'react-i18next';
import { Bell } from 'lucide-react';
import { Alert, Skeleton } from '@/components/ui';
import { EmptyState } from './EmptyState';
import { ReminderCard } from './ReminderCard';
import { useReminders } from '@/hooks/useReminders';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';

export interface VehicleRemindersTabProps {
  vehicleId: number;
}

export function VehicleRemindersTab({ vehicleId }: VehicleRemindersTabProps) {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useReminders(vehicleId);

  return (
    <div className="space-y-3 pt-2">
      {isLoading && (
        <>
          <Skeleton className="h-24 w-full rounded-lg" />
          <Skeleton className="h-24 w-full rounded-lg" />
        </>
      )}

      {error && <Alert variant="error">{errorMessage(error)}</Alert>}

      {data && data.length === 0 && (
        <EmptyState
          Icon={Bell}
          title={t('reminder.empty.title')}
          description={t('reminder.empty.description')}
        />
      )}

      {data && data.length > 0 && (
        <div className="space-y-3">
          {data.map((r) => (
            <ReminderCard key={r.id} reminder={r} />
          ))}
        </div>
      )}
    </div>
  );
}
