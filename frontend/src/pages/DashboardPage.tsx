import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { Car, ChevronRight, Euro, Fuel, Wrench } from 'lucide-react';
import { Alert, Card, CardContent, Skeleton } from '@/components/ui';
import { PageHeader } from '@/components/layout';
import { DashboardCharts, StatCard, UpcomingRemindersCard } from '@/components/features';
import { useDashboard } from '@/hooks/useDashboard';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useFormat } from '@/hooks/useFormat';

export function DashboardPage() {
  const { t } = useTranslation();
  const fmt = useFormat();
  const errorMessage = useApiErrorMessage();
  const dash = useDashboard();

  return (
    <div className="space-y-6">
      <PageHeader srTitle={t('nav.dashboard')} />

      {dash.error ? <Alert variant="error">{errorMessage(dash.error)}</Alert> : null}
      {!dash.error && dash.partial ? <Alert variant="warning">{t('dashboard.partial_totals')}</Alert> : null}

      {/* Stat grid */}
      {dash.isLoading ? (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-20 w-full rounded-lg" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
          <StatCard Icon={Car} label={t('dashboard.your_vehicles')} value={dash.vehicles.length} />
          <StatCard Icon={Euro} label={t('dashboard.total_cost')} value={fmt.currency(dash.totalCost)} />
          <StatCard Icon={Fuel} label={t('nav.refueling')} value={dash.totalRefuelings} />
          <StatCard Icon={Wrench} label={t('nav.maintenance')} value={dash.totalMaintenances} />
        </div>
      )}

      {!dash.isLoading && dash.excludedCount > 0 ? (
        <p className="text-sm text-muted-foreground">
          {t('dashboard.owned_only_hint', { count: dash.excludedCount })}
        </p>
      ) : null}

      <UpcomingRemindersCard />

      {/* Grafici solo a lista veicoli caricata e con almeno un veicolo proprio (stessa regola dei
          totali): altrimenti niente sezione e niente richiesta. Il backend filtra comunque da sé. */}
      {dash.vehicles.length > 0 ? <DashboardCharts /> : null}

      {/* Accesso alla lista veicoli: solo mobile, su desktop c'è la voce Veicoli nella sidebar */}
      <Link
        to="/vehicles"
        className="block rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-ring md:hidden"
      >
        <Card className="transition-colors hover:border-primary hover:bg-accent/40">
          <CardContent standalone className="flex items-center gap-4">
            <div className="rounded-md bg-primary/10 p-3">
              <Car className="size-6 text-primary" />
            </div>
            <div className="min-w-0 flex-1">
              <h2 className="text-base font-semibold">{t('dashboard.your_vehicles')}</h2>
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
