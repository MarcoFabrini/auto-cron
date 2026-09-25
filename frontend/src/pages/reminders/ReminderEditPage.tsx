import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Alert, Skeleton } from '@/components/ui';
import { PageHeader } from '@/components/layout';
import { ReminderForm } from '@/components/features';
import { useReminder, useUpdateReminder } from '@/hooks/useReminders';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useToast } from '@/hooks/useToast';

export function ReminderEditPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const reminderId = Number(id);
  const navigate = useNavigate();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const { data, isLoading, error } = useReminder(reminderId);
  const updateMutation = useUpdateReminder(reminderId);

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
        title={t('reminder.edit')}
        onBack={() => navigate(`/reminders/${reminderId}`)}
      />

      <ReminderForm
        vehicleId={data.vehicleId}
        defaultValues={data}
        isPending={updateMutation.isPending}
        error={updateMutation.error}
        onCancel={() => navigate(`/reminders/${reminderId}`)}
        onSubmit={(form) =>
          updateMutation.mutate(form, {
            onSuccess: () => {
              toast({ title: t('reminder.updated'), variant: 'success' });
              navigate(`/reminders/${reminderId}`, { replace: true });
            },
          })
        }
      />
    </div>
  );
}
