import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Fuel } from 'lucide-react';
import { Badge, Card, CardContent } from '@/components/ui';
import { FuelTypeBadge } from './FuelTypeBadge';
import { formatCurrency, formatDate, formatDecimal, formatKm } from '@/lib/format';
import type { Refueling } from '@/api/types/refueling';

export interface RefuelingCardProps {
  refueling: Refueling;
}

export function RefuelingCard({ refueling }: RefuelingCardProps) {
  const { t } = useTranslation();
  const r = refueling;

  return (
    <Card>
      <Link to={`/refueling/${r.id}`} className="block">
        <CardContent standalone className="flex items-start gap-4">
          <div className="rounded-md bg-primary/10 p-3">
            <Fuel className="size-5 text-primary" />
          </div>

          <div className="min-w-0 flex-1 space-y-1">
            <div className="flex items-center justify-between gap-2">
              <h3 className="truncate text-base font-semibold">
                {formatDecimal(Number.parseFloat(r.liters), 2)} L
              </h3>
              <span className="shrink-0 text-sm font-medium">{formatCurrency(r.totalCost)}</span>
            </div>
            <p className="text-sm text-muted-foreground">
              {t('refueling.price_per_liter')}: {formatCurrency(r.pricePerLiter)}
            </p>
            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
              <span>{formatDate(r.refueledAt)}</span>
              <span>·</span>
              <span>{formatKm(r.km)}</span>
              {r.station && (
                <>
                  <span>·</span>
                  <span className="truncate">{r.station}</span>
                </>
              )}
            </div>
            <div className="flex flex-wrap gap-1 pt-1">
              <FuelTypeBadge type={r.fuelType} />
              {r.fullTank && <Badge variant="success">{t('refueling.full_tank')}</Badge>}
            </div>
          </div>
        </CardContent>
      </Link>
    </Card>
  );
}
