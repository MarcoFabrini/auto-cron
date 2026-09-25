import { useTranslation } from 'react-i18next';
import { Receipt } from 'lucide-react';
import { Alert, Skeleton } from '@/components/ui';
import { EmptyState } from './EmptyState';
import { ExpenseCard } from './ExpenseCard';
import { useExpenses } from '@/hooks/useExpenses';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';

export interface VehicleExpensesTabProps {
  vehicleId: number;
}

export function VehicleExpensesTab({ vehicleId }: VehicleExpensesTabProps) {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useExpenses(vehicleId);

  return (
    <div className="space-y-3 pt-2">
      {isLoading && (
        <>
          <Skeleton className="h-24 w-full rounded-lg" />
          <Skeleton className="h-24 w-full rounded-lg" />
        </>
      )}

      {error && <Alert variant="error">{errorMessage(error)}</Alert>}

      {data && data.length === 0 && (
        <EmptyState
          Icon={Receipt}
          title={t('expense.empty.title')}
          description={t('expense.empty.description')}
        />
      )}

      {data && data.length > 0 && (
        <div className="space-y-3">
          {data.map((e) => (
            <ExpenseCard key={e.id} expense={e} />
          ))}
        </div>
      )}
    </div>
  );
}
