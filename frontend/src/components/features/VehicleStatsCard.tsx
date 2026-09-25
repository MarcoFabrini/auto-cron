import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import { FuelTypeBadge } from './FuelTypeBadge';
import { FUEL_TYPES, type FuelType } from '@/api/types/vehicle';
import type { VehicleStats } from '@/api/types/vehicleStats';
import { formatCurrency, formatDecimal, formatKm } from '@/lib/format';

export interface VehicleStatsCardProps {
  stats: VehicleStats;
}

const isFuelType = (v: string): v is FuelType => (FUEL_TYPES as readonly string[]).includes(v);

/**
 * Statistiche calcolate dal backend per un veicolo: km percorsi, costo al km, costo totale,
 * numero di registrazioni e consumo medio per carburante (km/l, null finché non ci sono
 * abbastanza pieni consecutivi per calcolarlo).
 */
export function VehicleStatsCard({ stats }: VehicleStatsCardProps) {
  const { t } = useTranslation();
  const consumption = Object.entries(stats.consumption).filter(([fuel]) => isFuelType(fuel));

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('vehicle.stats.title')}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <Item label={t('vehicle.stats.km_driven')} value={formatKm(stats.kmDriven)} />
          <Item
            label={t('vehicle.stats.cost_per_km')}
            value={stats.costPerKm === null ? '—' : formatCurrency(stats.costPerKm)}
          />
          <Item label={t('vehicle.stats.total_cost')} value={formatCurrency(stats.totals.cost)} />
          <Item label={t('vehicle.stats.refuelings')} value={stats.totals.refuelings} />
          <Item label={t('vehicle.stats.maintenances')} value={stats.totals.maintenances} />
          <Item label={t('vehicle.stats.expenses')} value={stats.totals.expenses} />
        </dl>

        {consumption.length > 0 && (
          <div className="space-y-2 border-t pt-3">
            <p className="text-sm font-medium">{t('vehicle.stats.consumption')}</p>
            <ul className="space-y-1.5 text-sm">
              {consumption.map(([fuel, value]) => (
                <li key={fuel} className="flex items-center justify-between gap-3">
                  <FuelTypeBadge type={fuel as FuelType} />
                  {value === null ? (
                    <span className="text-muted-foreground">{t('vehicle.stats.consumption_unknown')}</span>
                  ) : (
                    <span className="font-medium">{formatDecimal(value, 1)} km/l</span>
                  )}
                </li>
              ))}
            </ul>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function Item({ label, value }: { label: string; value: string | number }) {
  return (
    <div>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium">{value}</dd>
    </div>
  );
}
