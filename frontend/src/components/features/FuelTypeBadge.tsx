import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui';
import type { FuelType } from '@/api/types/vehicle';

/**
 * FuelTypeBadge feature — pill colorato per tipo carburante.
 *
 * @example
 * <FuelTypeBadge type="gasoline" />
 * <FuelTypeBadge type="lpg" />
 */
export interface FuelTypeBadgeProps {
  type: FuelType;
}

const variantByFuel: Record<FuelType, 'default' | 'secondary' | 'success' | 'warning' | 'outline'> = {
  gasoline: 'warning',
  diesel: 'secondary',
  lpg: 'success',
  cng: 'success',
  electric: 'success',
  hybrid: 'success',
  hydrogen: 'success',
  ethanol: 'success',
  biodiesel: 'success',
};

export function FuelTypeBadge({ type }: FuelTypeBadgeProps) {
  const { t } = useTranslation();
  return <Badge variant={variantByFuel[type]}>{t(`vehicle.fuel.${type}`)}</Badge>;
}
