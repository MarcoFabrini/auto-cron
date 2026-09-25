import { useTranslation } from 'react-i18next';
import { Fuel } from 'lucide-react';
import { Alert, Skeleton } from '@/components/ui';
import { EmptyState } from './EmptyState';
import { RefuelingCard } from './RefuelingCard';
import { useRefuelings } from '@/hooks/useRefuelings';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';

export interface VehicleRefuelingTabProps {
  vehicleId: number;
}

export function VehicleRefuelingTab({ vehicleId }: VehicleRefuelingTabProps) {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useRefuelings(vehicleId);

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
          Icon={Fuel}
          title={t('refueling.empty.title')}
          description={t('refueling.empty.description')}
        />
      )}

      {data && data.length > 0 && (
        <div className="space-y-3">
          {data.map((r) => (
            <RefuelingCard key={r.id} refueling={r} />
          ))}
        </div>
      )}
    </div>
  );
}
