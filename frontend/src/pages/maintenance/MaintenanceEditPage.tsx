import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Alert, Skeleton } from '@/components/ui';
import { PageHeader } from '@/components/layout';
import { MaintenanceForm } from '@/components/features';
import { useMaintenance, useUpdateMaintenance } from '@/hooks/useMaintenances';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useToast } from '@/hooks/useToast';

export function MaintenanceEditPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const maintenanceId = Number(id);
  const navigate = useNavigate();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const { data, isLoading, error } = useMaintenance(maintenanceId);
  const updateMutation = useUpdateMaintenance(maintenanceId);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-96 w-full rounded-lg" />
      </div>
    );
  }
  if (error) return <Alert variant="error">{errorMessage(error)}</Alert>;
  if (!data) return null;

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('maintenance.edit')}
        description={t(`maintenance.type_options.${data.type}`)}
        onBack={() => navigate(`/maintenance/${maintenanceId}`)}
      />

      <MaintenanceForm
        vehicleId={data.vehicleId}
        defaultValues={data}
        isPending={updateMutation.isPending}
        error={updateMutation.error}
        onCancel={() => navigate(`/maintenance/${maintenanceId}`)}
        onSubmit={(form) =>
          updateMutation.mutate(form, {
            onSuccess: () => {
              toast({ title: t('maintenance.updated'), variant: 'success' });
              navigate(`/maintenance/${maintenanceId}`, { replace: true });
            },
          })
        }
      />
    </div>
  );
}
