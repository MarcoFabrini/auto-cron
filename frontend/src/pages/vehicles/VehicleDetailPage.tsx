import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Pencil, Trash2 } from 'lucide-react';
import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Skeleton,
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui';
import { PageHeader } from '@/components/layout';
import {
  AttachmentsSection,
  ConfirmDialog,
  FuelTypeBadge,
  VehicleExpensesTab,
  VehicleMaintenanceTab,
  VehicleRefuelingTab,
  VehicleRemindersTab,
  VehicleShareCard,
  VehicleStatsCard,
} from '@/components/features';
import { useDeleteVehicle, useVehicle, useVehicleStats } from '@/hooks/useVehicles';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useAuthStore } from '@/stores/useAuthStore';
import { useToast } from '@/hooks/useToast';
import { formatKm } from '@/lib/format';

/**
 * VehicleDetailPage — read-only sezione info + tab placeholder.
 * Future tab: maintenance, refueling, expenses, reminders, attachments.
 */
export function VehicleDetailPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const vehicleId = Number(id);
  const navigate = useNavigate();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const { data, isLoading, error } = useVehicle(vehicleId);
  const stats = useVehicleStats(vehicleId);
  const deleteMutation = useDeleteVehicle();
  const [confirmOpen, setConfirmOpen] = useState(false);

  const currentUser = useAuthStore((s) => s.user);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-48 w-full rounded-lg" />
      </div>
    );
  }

  if (error) return <Alert variant="error">{errorMessage(error)}</Alert>;
  if (!data) return null;

  const archived = Boolean(data.archivedAt);
  // Permessi sul singolo veicolo (proprietario, org owner/admin o sola lettura
  // via condivisione): chi ha solo lo share in lettura non vede le azioni.
  const canEdit = data.permissions?.canEdit ?? false;
  const canDelete = data.permissions?.canDelete ?? false;
  const canShare = data.permissions?.canShare ?? false;

  return (
    <div className="space-y-6">
      <PageHeader
        title={data.name}
        description={`${data.brand} ${data.model} · ${data.year}`}
        onBack={() => navigate('/vehicles')}
        action={
          canEdit || canDelete ? (
            <>
              {canEdit && (
                <Button variant="outline" size="icon" asChild aria-label={t('actions.edit')}>
                  <Link to={`/vehicles/${data.id}/edit`}>
                    <Pencil />
                  </Link>
                </Button>
              )}
              {canDelete && (
                <Button
                  variant="destructive"
                  size="icon"
                  onClick={() => setConfirmOpen(true)}
                  aria-label={t('actions.delete')}
                >
                  <Trash2 />
                </Button>
              )}
            </>
          ) : undefined
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>{t('vehicle.info')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap gap-2">
            <Badge variant="outline">{t(`vehicle.type_options.${data.type}`)}</Badge>
            <FuelTypeBadge type={data.fuelType} />
            {data.secondaryFuelType && <FuelTypeBadge type={data.secondaryFuelType} />}
            {archived && <Badge variant="destructive">{t('vehicle.archived')}</Badge>}
          </div>
          <dl className="grid grid-cols-2 gap-3 text-sm">
            {data.licensePlate && (
              <div>
                <dt className="text-muted-foreground">{t('vehicle.license_plate')}</dt>
                <dd className="font-medium">{data.licensePlate}</dd>
              </div>
            )}
            {data.vin && (
              <div>
                <dt className="text-muted-foreground">{t('vehicle.vin')}</dt>
                <dd className="font-medium">{data.vin}</dd>
              </div>
            )}
            <div>
              <dt className="text-muted-foreground">{t('vehicle.current_km')}</dt>
              <dd className="font-medium">
                {formatKm(stats.data?.currentKm ?? data.initialKm)}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('vehicle.initial_km')}</dt>
              <dd className="font-medium">{formatKm(data.initialKm)}</dd>
            </div>
          </dl>
          {data.notes && (
            <div className="rounded-md bg-muted px-3 py-2 text-sm">{data.notes}</div>
          )}
        </CardContent>
      </Card>

      {stats.data && <VehicleStatsCard stats={stats.data} />}

      <Tabs defaultValue="maintenance" className="w-full">
        <TabsList className="flex w-full justify-start overflow-x-auto no-scrollbar [&>button]:shrink-0 sm:grid sm:grid-cols-5">
          <TabsTrigger value="maintenance">{t('nav.maintenance')}</TabsTrigger>
          <TabsTrigger value="refueling">{t('nav.refueling')}</TabsTrigger>
          <TabsTrigger value="expenses">{t('nav.expenses')}</TabsTrigger>
          <TabsTrigger value="reminders">{t('nav.reminders')}</TabsTrigger>
          <TabsTrigger value="attachments">{t('nav.attachments')}</TabsTrigger>
        </TabsList>
        <TabsContent value="maintenance">
          <VehicleMaintenanceTab vehicleId={data.id} />
        </TabsContent>
        <TabsContent value="refueling">
          <VehicleRefuelingTab vehicleId={data.id} />
        </TabsContent>
        <TabsContent value="expenses">
          <VehicleExpensesTab vehicleId={data.id} />
        </TabsContent>
        <TabsContent value="reminders">
          <VehicleRemindersTab vehicleId={data.id} />
        </TabsContent>
        <TabsContent value="attachments">
          <AttachmentsSection entityType="vehicle" entityId={data.id} />
        </TabsContent>
      </Tabs>

      {canShare && currentUser && (
        <VehicleShareCard vehicleId={data.id} currentUserId={currentUser.id} />
      )}

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t('vehicle.delete.title')}
        description={t('vehicle.delete.description')}
        isPending={deleteMutation.isPending}
        onConfirm={() =>
          deleteMutation.mutate(vehicleId, {
            onSuccess: () => {
              toast({ title: t('vehicle.deleted'), variant: 'success' });
              navigate('/vehicles', { replace: true });
            },
            onError: (e) =>
              toast({ title: errorMessage(e), variant: 'error' }),
          })
        }
        confirmLabel={t('actions.delete')}
      />
    </div>
  );
}
