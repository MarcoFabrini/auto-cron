import { useTranslation } from 'react-i18next';
import { Car, ChevronRight } from 'lucide-react';
import { Card, CardContent, Heading } from '@/components/ui';
import { FuelTypeBadge } from './FuelTypeBadge';
import type { Vehicle } from '@/api/types/vehicle';

/**
 * VehiclePicker — griglia di card veicolo cliccabili usata nelle pagine
 * filtrate per veicolo (Manutenzioni/Rifornimenti/Spese/Promemoria) al posto
 * del vecchio Select. Il click seleziona il veicolo (apre la vista in grande).
 */
export interface VehiclePickerProps {
  vehicles: Vehicle[];
  onSelect: (vehicleId: number) => void;
  /** Omette il titolo "Scegli un veicolo" quando il contenitore ne ha già uno (es. il titolo del foglio di aggiunta rapida). */
  hideHeading?: boolean;
}

export function VehiclePicker({ vehicles, onSelect, hideHeading = false }: VehiclePickerProps) {
  const { t } = useTranslation();

  return (
    <div className="space-y-3">
      {!hideHeading && <Heading level={3}>{t('vehicle_picker.title')}</Heading>}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {vehicles.map((v) => (
          <button
            key={v.id}
            type="button"
            onClick={() => onSelect(v.id)}
            className="rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <Card className="h-full transition-colors hover:border-primary hover:bg-accent/40">
              <CardContent standalone className="flex items-center gap-4">
                <div className="rounded-md bg-primary/10 p-3">
                  <Car className="size-6 text-primary" />
                </div>
                <div className="min-w-0 flex-1 space-y-1">
                  <h3 className="truncate text-base font-semibold">{v.name}</h3>
                  <p className="truncate text-sm text-muted-foreground">
                    {v.brand} {v.model} · {v.year}
                    {v.licensePlate ? ` · ${v.licensePlate}` : ''}
                  </p>
                  <div className="flex flex-wrap gap-1">
                    <FuelTypeBadge type={v.fuelType} />
                    {v.secondaryFuelType && <FuelTypeBadge type={v.secondaryFuelType} />}
                  </div>
                </div>
                <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
              </CardContent>
            </Card>
          </button>
        ))}
      </div>
    </div>
  );
}
