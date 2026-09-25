import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';
import { Fuel, Plus } from 'lucide-react';
import { Button } from '@/components/ui';
import { PageHeader } from '@/components/layout';
import { EmptyState, RefuelingCard, VehicleScopedList } from '@/components/features';
import { useRefuelings } from '@/hooks/useRefuelings';
import { useVehicleIdParam } from '@/hooks/useVehicleIdParam';
import { useVehicleName } from '@/hooks/useVehicleName';

export function RefuelingListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { vehicleId, setParams } = useVehicleIdParam();
  const vehicleName = useVehicleName(vehicleId);
  const { data, isLoading, error } = useRefuelings(vehicleId);

  // Link condiviso: nell'header è nascosto su mobile (c'è il "+" della barra), nello stato vuoto no.
  const newLink = (
    <Link to={`/refueling/new?vehicleId=${vehicleId}`}>
      <Plus />
      {t('refueling.new')}
    </Link>
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title={vehicleId > 0 ? vehicleName : undefined}
        onBack={vehicleId > 0 ? () => navigate(`/vehicles/${vehicleId}`) : undefined}
        action={
          vehicleId > 0 && (
            <Button asChild className="hidden md:inline-flex">
              {newLink}
            </Button>
          )
        }
      />

      <VehicleScopedList
        vehicleId={vehicleId}
        onSelectVehicle={(id) => setParams({ vehicleId: String(id) })}
        items={data}
        isLoading={isLoading}
        error={error}
        empty={
          <EmptyState
            Icon={Fuel}
            title={t('refueling.empty.title')}
            description={t('refueling.empty.description')}
            action={
              <Button asChild>{newLink}</Button>
            }
          />
        }
        renderItem={(r) => <RefuelingCard key={r.id} refueling={r} />}
      />
    </div>
  );
}
