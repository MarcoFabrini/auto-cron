import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/layout';
import { ReminderForm, SelectVehiclePrompt } from '@/components/features';
import { useCreateReminder } from '@/hooks/useReminders';
import { useToast } from '@/hooks/useToast';
import { useVehicleIdParam } from '@/hooks/useVehicleIdParam';

export function ReminderNewPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { toast } = useToast();
  const { vehicleId } = useVehicleIdParam();
  const mutation = useCreateReminder();

  if (vehicleId === 0) {
    return <SelectVehiclePrompt i18nPrefix="reminder" listPath="/reminders" />;
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('reminder.new')}
        onBack={() => navigate(`/reminders?vehicleId=${vehicleId}`)}
      />

      <ReminderForm
        vehicleId={vehicleId}
        isPending={mutation.isPending}
        error={mutation.error}
        onCancel={() => navigate(`/reminders?vehicleId=${vehicleId}`)}
        onSubmit={(data) =>
          mutation.mutate(data, {
            onSuccess: (created) => {
              toast({ title: t('reminder.created'), variant: 'success' });
              navigate(`/reminders/${created.id}`, { replace: true });
            },
          })
        }
      />
    </div>
  );
}
