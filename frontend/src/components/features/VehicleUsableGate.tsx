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
 * Guard per le pagine che scrivono su un veicolo, sia "nuovo record" (`?vehicleId=N`) sia
 * "modifica record": il form si apre solo quando il veicolo è stato caricato ed è visibile
 * all'utente, modificabile (non condiviso in sola lettura) e non archiviato. Finché non è noto
 * (caricamento, query in pausa) c'è uno skeleton, e se la query fallisce (403/404) l'errore:
 * un permesso ignoto non vale "modificabile", altrimenti il form lampeggia o resta a un
 * utente in sola lettura e l'errore comparirebbe solo al submit (o mai).
 */
export function VehicleUsableGate({ vehicleId, children }: VehicleUsableGateProps) {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useVehicle(vehicleId);

  if (error) return <Alert variant="error">{errorMessage(error)}</Alert>;
  if (isLoading || !data) return <Skeleton className="h-48 w-full rounded-lg" />;
  if (data.permissions.canEdit !== true) {
    return <Alert variant="warning">{t('vehicle.read_only_no_changes')}</Alert>;
  }
  if (data.archivedAt) return <Alert variant="warning">{t('vehicle.archived_no_new_records')}</Alert>;

  return <>{children}</>;
}
