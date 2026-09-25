import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/layout';
import { RefuelingForm, SelectVehiclePrompt } from '@/components/features';
import { useCreateRefueling } from '@/hooks/useRefuelings';
import { useVehicle, useVehicleStats } from '@/hooks/useVehicles';
import { useToast } from '@/hooks/useToast';
import { useVehicleIdParam } from '@/hooks/useVehicleIdParam';

export function RefuelingNewPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { toast } = useToast();
  const { vehicleId } = useVehicleIdParam();
  const vehicleQuery = useVehicle(vehicleId);
  const statsQuery = useVehicleStats(vehicleId);
  const mutation = useCreateRefueling();

  if (vehicleId === 0) {
    return <SelectVehiclePrompt i18nPrefix="refueling" listPath="/refueling" />;
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('refueling.new')}
        onBack={() => navigate(`/refueling?vehicleId=${vehicleId}`)}
      />

      <RefuelingForm
        vehicleId={vehicleId}
        defaultFuelType={vehicleQuery.data?.fuelType}
        defaultKm={statsQuery.data?.currentKm}
        isPending={mutation.isPending}
        error={mutation.error}
        onCancel={() => navigate(`/refueling?vehicleId=${vehicleId}`)}
        onSubmit={(data) =>
          mutation.mutate(data, {
            onSuccess: (created) => {
              toast({ title: t('refueling.created'), variant: 'success' });
              navigate(`/refueling/${created.id}`, { replace: true });
            },
          })
        }
      />
    </div>
  );
}
