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
import { AttachmentsSection, ConfirmDialog, FuelTypeBadge } from '@/components/features';
import { useDeleteRefueling, useRefueling } from '@/hooks/useRefuelings';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useToast } from '@/hooks/useToast';
import { formatCurrency, formatDate, formatDecimal, formatKm } from '@/lib/format';

export function RefuelingDetailPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const refuelingId = Number(id);
  const navigate = useNavigate();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const { data, isLoading, error } = useRefueling(refuelingId);
  const deleteMutation = useDeleteRefueling(data?.vehicleId ?? 0);
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

  const litersNum = Number.parseFloat(data.liters);

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('refueling.detail_title', {
          liters: formatDecimal(litersNum, 2),
        })}
        description={formatDate(data.refueledAt)}
        onBack={() => navigate(`/refueling?vehicleId=${data.vehicleId}`)}
        action={
          <>
            <Button variant="outline" size="icon" asChild aria-label={t('actions.edit')}>
              <Link to={`/refueling/${data.id}/edit`}>
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
          <CardTitle>{t('refueling.info')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap gap-2">
            <FuelTypeBadge type={data.fuelType} />
            {data.fullTank && <Badge variant="success">{t('refueling.full_tank')}</Badge>}
          </div>
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <dt className="text-muted-foreground">{t('refueling.km')}</dt>
              <dd className="font-medium">{formatKm(data.km)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('refueling.liters')}</dt>
              <dd className="font-medium">{formatDecimal(litersNum, 2)} L</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('refueling.price_per_liter')}</dt>
              <dd className="font-medium">{formatCurrency(data.pricePerLiter)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('refueling.total_cost')}</dt>
              <dd className="font-medium">{formatCurrency(data.totalCost)}</dd>
            </div>
            {data.station && (
              <div className="col-span-2">
                <dt className="text-muted-foreground">{t('refueling.station')}</dt>
                <dd className="font-medium">{data.station}</dd>
              </div>
            )}
          </dl>
          {data.notes && (
            <div className="rounded-md bg-muted px-3 py-2 text-sm">{data.notes}</div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('attachment.title')}</CardTitle>
          <CardDescription>{t('attachment.description')}</CardDescription>
        </CardHeader>
        <CardContent>
          <AttachmentsSection entityType="refueling" entityId={data.id} />
        </CardContent>
      </Card>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t('refueling.delete.title')}
        description={t('refueling.delete.description')}
        isPending={deleteMutation.isPending}
        confirmLabel={t('actions.delete')}
        onConfirm={() =>
          deleteMutation.mutate(refuelingId, {
            onSuccess: () => {
              toast({ title: t('refueling.deleted'), variant: 'success' });
              navigate(`/refueling?vehicleId=${data.vehicleId}`, { replace: true });
            },
            onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
          })
        }
      />
    </div>
  );
}
