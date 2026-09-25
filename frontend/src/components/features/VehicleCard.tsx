import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Car } from 'lucide-react';
import { Badge, Card, CardContent } from '@/components/ui';
import { FuelTypeBadge } from './FuelTypeBadge';
import type { Vehicle } from '@/api/types/vehicle';

/**
 * VehicleCard feature — riepilogo veicolo, click apre detail.
 *
 * Mostra: icona + nome + brand/model + plate + fuel badges + archived state.
 *
 * @example
 * <VehicleCard vehicle={v} />
 */
export interface VehicleCardProps {
  vehicle: Vehicle;
}

export function VehicleCard({ vehicle }: VehicleCardProps) {
  const { t } = useTranslation();
  // archivedAt non è nel group vehicle:list → undefined in lista. Boolean() gestisce undefined+null.
  const archived = Boolean(vehicle.archivedAt);

  return (
    <Card>
      <Link to={`/vehicles/${vehicle.id}`} className="block">
        <CardContent standalone className="flex items-center gap-4">
          <div className="rounded-md bg-primary/10 p-3">
            <Car className="size-6 text-primary" />
          </div>

          <div className="min-w-0 flex-1 space-y-1">
            <div className="flex items-center gap-2">
              <h3 className="truncate text-base font-semibold">{vehicle.name}</h3>
              {archived && <Badge variant="outline">{t('vehicle.archived')}</Badge>}
            </div>
            <p className="truncate text-sm text-muted-foreground">
              {vehicle.brand} {vehicle.model} · {vehicle.year}
              {vehicle.licensePlate ? ` · ${vehicle.licensePlate}` : ''}
            </p>
            <div className="flex flex-wrap gap-1">
              <FuelTypeBadge type={vehicle.fuelType} />
              {vehicle.secondaryFuelType && <FuelTypeBadge type={vehicle.secondaryFuelType} />}
            </div>
          </div>
        </CardContent>
      </Link>
    </Card>
  );
}
