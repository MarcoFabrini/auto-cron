import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/layout';
import { VehicleForm } from '@/components/features';
import { useCreateVehicle } from '@/hooks/useVehicles';
import { useToast } from '@/hooks/useToast';

/**
 * VehicleNewPage — form create. Su success: toast + redirect detail.
 */
export function VehicleNewPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { toast } = useToast();
  const mutation = useCreateVehicle();

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('vehicle.new')}
        onBack={() => navigate('/vehicles')}
      />

      <VehicleForm
        isPending={mutation.isPending}
        error={mutation.error}
        onCancel={() => navigate('/vehicles')}
        onSubmit={(data) =>
          mutation.mutate(data, {
            onSuccess: (vehicle) => {
              toast({ title: t('vehicle.created'), variant: 'success' });
              navigate(`/vehicles/${vehicle.id}`, { replace: true });
            },
          })
        }
      />
    </div>
  );
}
