import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Pencil, Trash2 } from 'lucide-react';
import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Skeleton,
} from '@/components/ui';
import { PageHeader } from '@/components/layout';
import { ConfirmDialog } from '@/components/features';
import { useDeleteExpense, useExpense } from '@/hooks/useExpenses';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useToast } from '@/hooks/useToast';
import { formatCurrency, formatDate } from '@/lib/format';

export function ExpenseDetailPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const expenseId = Number(id);
  const navigate = useNavigate();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const { data, isLoading, error } = useExpense(expenseId);
  const deleteMutation = useDeleteExpense(data?.vehicleId ?? 0);
  const [confirmOpen, setConfirmOpen] = useState(false);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-48 w-full rounded-lg" />
      </div>
    );
  }
  if (error) return <Alert variant="error">{errorMessage(error)}</Alert>;
  if (!data) return null;

  return (
    <div className="space-y-6">
      <PageHeader
        title={t(`expense.category_options.${data.category}`)}
        description={formatCurrency(data.amount)}
        onBack={() => navigate(`/expenses?vehicleId=${data.vehicleId}`)}
        action={
          <>
            <Button variant="outline" size="icon" asChild aria-label={t('actions.edit')}>
              <Link to={`/expenses/${data.id}/edit`}>
                <Pencil />
              </Link>
            </Button>
            <Button
              variant="destructive"
              size="icon"
              onClick={() => setConfirmOpen(true)}
              aria-label={t('actions.delete')}
            >
              <Trash2 />
            </Button>
          </>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>{t('expense.info')}</CardTitle>
          <CardDescription>{data.description}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {data.recurring && data.recurringPeriod && (
            <Badge variant="secondary">{t(`expense.recurring_options.${data.recurringPeriod}`)}</Badge>
          )}
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <dt className="text-muted-foreground">{t('expense.occurred_at')}</dt>
              <dd className="font-medium">{formatDate(data.occurredAt)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('expense.amount')}</dt>
              <dd className="font-medium">{formatCurrency(data.amount)}</dd>
            </div>
          </dl>
          {data.notes && <div className="rounded-md bg-muted px-3 py-2 text-sm">{data.notes}</div>}
        </CardContent>
      </Card>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t('expense.delete.title')}
        description={t('expense.delete.description')}
        isPending={deleteMutation.isPending}
        confirmLabel={t('actions.delete')}
        onConfirm={() =>
          deleteMutation.mutate(expenseId, {
            onSuccess: () => {
              toast({ title: t('expense.deleted'), variant: 'success' });
              navigate(`/expenses?vehicleId=${data.vehicleId}`, { replace: true });
            },
            onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
          })
        }
      />
    </div>
  );
}
