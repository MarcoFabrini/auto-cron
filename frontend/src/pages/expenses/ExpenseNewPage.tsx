import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/layout';
import { ExpenseForm, SelectVehiclePrompt } from '@/components/features';
import { useCreateExpense } from '@/hooks/useExpenses';
import { useToast } from '@/hooks/useToast';
import { useVehicleIdParam } from '@/hooks/useVehicleIdParam';

export function ExpenseNewPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { toast } = useToast();
  const { vehicleId } = useVehicleIdParam();
  const mutation = useCreateExpense();

  if (vehicleId === 0) {
    return <SelectVehiclePrompt i18nPrefix="expense" listPath="/expenses" />;
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('expense.new')}
        onBack={() => navigate(`/expenses?vehicleId=${vehicleId}`)}
      />

      <ExpenseForm
        vehicleId={vehicleId}
        isPending={mutation.isPending}
        error={mutation.error}
        onCancel={() => navigate(`/expenses?vehicleId=${vehicleId}`)}
        onSubmit={(data) =>
          mutation.mutate(data, {
            onSuccess: (created) => {
              toast({ title: t('expense.created'), variant: 'success' });
              navigate(`/expenses/${created.id}`, { replace: true });
            },
          })
        }
      />
    </div>
  );
}
