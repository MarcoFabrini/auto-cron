import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, Skeleton } from '@/components/ui';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useVehicle } from '@/hooks/useVehicles';

export interface VehicleUsableGateProps {
  vehicleId: number;
  children: ReactNode;
}

/**
 * Guard per le pagine "nuovo record" (`?vehicleId=N`): il form si apre solo se il veicolo esiste, è
 * visibile all'utente, modificabile (non condiviso in sola lettura) e non è archiviato. Altrimenti
 * l'errore comparirebbe solo al submit (o mai).
 */
export function VehicleUsableGate({ vehicleId, children }: VehicleUsableGateProps) {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useVehicle(vehicleId);

  if (isLoading) return <Skeleton className="h-48 w-full rounded-lg" />;
  if (error) return <Alert variant="error">{errorMessage(error)}</Alert>;
  if (data && !data.permissions.canEdit) {
    return <Alert variant="warning">{t('vehicle.read_only_no_changes')}</Alert>;
  }
  if (data?.archivedAt) return <Alert variant="warning">{t('vehicle.archived_no_new_records')}</Alert>;

  return <>{children}</>;
}
