import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Receipt } from 'lucide-react';
import { Badge, Card, CardContent } from '@/components/ui';
import { formatCurrency, formatDate } from '@/lib/format';
import type { Expense } from '@/api/types/expense';

export interface ExpenseCardProps {
  expense: Expense;
}

export function ExpenseCard({ expense }: ExpenseCardProps) {
  const { t } = useTranslation();
  const e = expense;

  return (
    <Card>
      <Link to={`/expenses/${e.id}`} className="block">
        <CardContent standalone className="flex items-start gap-4">
          <div className="rounded-md bg-primary/10 p-3">
            <Receipt className="size-5 text-primary" />
          </div>

          <div className="min-w-0 flex-1 space-y-1">
            <div className="flex items-center justify-between gap-2">
              <h3 className="truncate text-base font-semibold">
                {t(`expense.category_options.${e.category}`)}
              </h3>
              <span className="shrink-0 text-sm font-medium">{formatCurrency(e.amount)}</span>
            </div>
            <p className="line-clamp-2 text-sm text-muted-foreground">{e.description}</p>
            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
              <span>{formatDate(e.occurredAt)}</span>
              {e.recurring && e.recurringPeriod && (
                <Badge variant="secondary" className="ml-auto">
                  {t(`expense.recurring_options.${e.recurringPeriod}`)}
                </Badge>
              )}
            </div>
          </div>
        </CardContent>
      </Link>
    </Card>
  );
}
