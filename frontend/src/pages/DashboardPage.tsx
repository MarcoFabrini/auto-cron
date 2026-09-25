import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { Car, ChevronRight, Euro, Fuel, Wrench } from 'lucide-react';
import { Alert, Card, CardContent, Skeleton } from '@/components/ui';
import { StatCard, UpcomingRemindersCard } from '@/components/features';
import { useDashboard } from '@/hooks/useDashboard';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { formatCurrency } from '@/lib/format';

export function DashboardPage() {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const dash = useDashboard();

  return (
    <div className="space-y-6">
      {dash.error ? <Alert variant="error">{errorMessage(dash.error)}</Alert> : null}

      {/* Stat grid */}
      {dash.isLoading ? (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-20 w-full rounded-lg" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
          <StatCard Icon={Car} label={t('nav.vehicles')} value={dash.vehicles.length} />
          <StatCard Icon={Euro} label={t('dashboard.total_cost')} value={formatCurrency(dash.totalCost)} />
          <StatCard Icon={Fuel} label={t('nav.refueling')} value={dash.totalRefuelings} />
          <StatCard Icon={Wrench} label={t('nav.maintenance')} value={dash.totalMaintenances} />
        </div>
      )}

      <UpcomingRemindersCard />

      {/* Accesso alla lista veicoli (la home ospiter� grafici/info in futuro) */}
      <Link
        to="/vehicles"
        className="block rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        <Card className="transition-colors hover:border-primary hover:bg-accent/40">
          <CardContent standalone className="flex items-center gap-4">
            <div className="rounded-md bg-primary/10 p-3">
              <Car className="size-6 text-primary" />
            </div>
            <div className="min-w-0 flex-1">
              <h3 className="text-base font-semibold">{t('dashboard.your_vehicles')}</h3>
              {!dash.isLoading && (
                <p className="text-sm text-muted-foreground">
                  {t('dashboard.vehicle_count', { count: dash.vehicles.length })}
                </p>
              )}
            </div>
            <ChevronRight className="size-5 shrink-0 text-muted-foreground" />
          </CardContent>
        </Card>
      </Link>
    </div>
  );
}
