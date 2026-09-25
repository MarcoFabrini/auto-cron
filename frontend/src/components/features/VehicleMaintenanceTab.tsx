import { useTranslation } from 'react-i18next';
import { Wrench } from 'lucide-react';
import { Alert, Skeleton } from '@/components/ui';
import { EmptyState } from './EmptyState';
import { MaintenanceCard } from './MaintenanceCard';
import { useMaintenances } from '@/hooks/useMaintenances';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';

/**
 * VehicleMaintenanceTab — slot per Tab dentro VehicleDetailPage.
 * Riusabile per future tab refueling/expenses pattern.
 */
export interface VehicleMaintenanceTabProps {
  vehicleId: number;
}

export function VehicleMaintenanceTab({ vehicleId }: VehicleMaintenanceTabProps) {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useMaintenances(vehicleId);

  return (
    <div className="space-y-3 pt-2">
      {isLoading && (
        <>
          <Skeleton className="h-20 w-full rounded-lg" />
          <Skeleton className="h-20 w-full rounded-lg" />
        </>
      )}

      {error && <Alert variant="error">{errorMessage(error)}</Alert>}

      {data && data.length === 0 && (
        <EmptyState
          Icon={Wrench}
          title={t('maintenance.empty.title')}
          description={t('maintenance.empty.description')}
        />
      )}

      {data && data.length > 0 && (
        <div className="space-y-3">
          {data.map((m) => (
            <MaintenanceCard key={m.id} maintenance={m} />
          ))}
        </div>
      )}
    </div>
  );
}
