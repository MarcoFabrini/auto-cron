import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Wrench } from 'lucide-react';
import { Badge, Card, CardContent } from '@/components/ui';
import { formatCurrency, formatDate, formatKm } from '@/lib/format';
import type { Maintenance } from '@/api/types/maintenance';

/**
 * MaintenanceCard feature — riepilogo manutenzione, click apre detail.
 */
export interface MaintenanceCardProps {
  maintenance: Maintenance;
}

export function MaintenanceCard({ maintenance }: MaintenanceCardProps) {
  const { t } = useTranslation();
  const m = maintenance;

  return (
    <Card>
      <Link to={`/maintenance/${m.id}`} className="block">
        <CardContent standalone className="flex items-start gap-4">
          <div className="rounded-md bg-primary/10 p-3">
            <Wrench className="size-5 text-primary" />
          </div>

          <div className="min-w-0 flex-1 space-y-1">
            <div className="flex items-center justify-between gap-2">
              <h3 className="truncate text-base font-semibold">
                {t(`maintenance.type_options.${m.type}`)}
              </h3>
              <span className="shrink-0 text-sm font-medium">{formatCurrency(m.cost)}</span>
            </div>
            <p className="line-clamp-2 text-sm text-muted-foreground">{m.description}</p>
            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
              <span>{formatDate(m.performedAt)}</span>
              <span>·</span>
              <span>{formatKm(m.km)}</span>
              {m.workshop && (
                <>
                  <span>·</span>
                  <span className="truncate">{m.workshop}</span>
                </>
              )}
              <Badge variant={m.category === 'scheduled' ? 'success' : 'warning'} className="ml-auto">
                {t(`maintenance.category_options.${m.category}`)}
              </Badge>
            </div>
          </div>
        </CardContent>
      </Link>
    </Card>
  );
}
