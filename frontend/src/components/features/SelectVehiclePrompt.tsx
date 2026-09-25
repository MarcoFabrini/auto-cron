import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Car } from 'lucide-react';
import { Button } from '@/components/ui';
import { EmptyState } from './EmptyState';

/**
 * SelectVehiclePrompt — guard per le pagine "new" aperte senza `?vehicleId=N`:
 * EmptyState con CTA verso la lista entità (dove si sceglie il veicolo).
 */
export interface SelectVehiclePromptProps {
  /** Namespace i18n dell'entità (es. 'maintenance' → `maintenance.select_vehicle_first.*`). */
  i18nPrefix: string;
  /** Rotta della lista entità, es. '/maintenance'. */
  listPath: string;
}

export function SelectVehiclePrompt({ i18nPrefix, listPath }: SelectVehiclePromptProps) {
  const { t } = useTranslation();

  return (
    <EmptyState
      Icon={Car}
      title={t(`${i18nPrefix}.select_vehicle_first.title`)}
      description={t(`${i18nPrefix}.select_vehicle_first.description`)}
      action={
        <Button asChild>
          <Link to={listPath}>{t('vehicle_picker.title')}</Link>
        </Button>
      }
    />
  );
}
