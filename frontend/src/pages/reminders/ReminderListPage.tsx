import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router-dom';
import { Bell, Plus } from 'lucide-react';
import { Button } from '@/components/ui';
import { PageHeader } from '@/components/layout';
import { EmptyState, ReminderCard, VehicleScopedList } from '@/components/features';
import { useReminders } from '@/hooks/useReminders';
import { useVehicleIdParam } from '@/hooks/useVehicleIdParam';
import { useVehicleName } from '@/hooks/useVehicleName';
import { useCanEditVehicle } from '@/hooks/useVehicles';

export function ReminderListPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { vehicleId, setParams } = useVehicleIdParam();
  const vehicleName = useVehicleName(vehicleId);
  // Veicolo condiviso in sola lettura: nessun "nuovo".
  const canEdit = useCanEditVehicle(vehicleId);
  const { data, isLoading, error } = useReminders(vehicleId);

  // Link condiviso: nell'header è nascosto su mobile (c'è il "+" della barra), nello stato vuoto no.
  const newLink = (
    <Link to={`/reminders/new?vehicleId=${vehicleId}`}>
      <Plus />
      {t('reminder.new')}
    </Link>
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title={vehicleId > 0 ? vehicleName : undefined}
        onBack={vehicleId > 0 ? () => navigate(`/vehicles/${vehicleId}`) : undefined}
        action={
          vehicleId > 0 && canEdit && (
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
            Icon={Bell}
            title={t('reminder.empty.title')}
            description={t('reminder.empty.description')}
            action={canEdit ? <Button asChild>{newLink}</Button> : undefined}
          />
        }
        renderItem={(r) => <ReminderCard key={r.id} reminder={r} />}
      />
    </div>
  );
}
