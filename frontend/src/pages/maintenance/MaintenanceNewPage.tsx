import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/layout';
import { MaintenanceForm, SelectVehiclePrompt } from '@/components/features';
import { useCreateMaintenance } from '@/hooks/useMaintenances';
import { useVehicleStats } from '@/hooks/useVehicles';
import { useToast } from '@/hooks/useToast';
import { useVehicleIdParam } from '@/hooks/useVehicleIdParam';

export function MaintenanceNewPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { toast } = useToast();
  const { vehicleId } = useVehicleIdParam();
  const statsQuery = useVehicleStats(vehicleId);
  const mutation = useCreateMaintenance();

  if (vehicleId === 0) {
    return <SelectVehiclePrompt i18nPrefix="maintenance" listPath="/maintenance" />;
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('maintenance.new')}
        onBack={() => navigate(`/maintenance?vehicleId=${vehicleId}`)}
      />

      <MaintenanceForm
        vehicleId={vehicleId}
        defaultKm={statsQuery.data?.currentKm}
        isPending={mutation.isPending}
        error={mutation.error}
        onCancel={() => navigate(`/maintenance?vehicleId=${vehicleId}`)}
        onSubmit={(data) =>
          mutation.mutate(data, {
            onSuccess: (created) => {
              toast({ title: t('maintenance.created'), variant: 'success' });
              navigate(`/maintenance/${created.id}`, { replace: true });
            },
          })
        }
      />
    </div>
  );
}
