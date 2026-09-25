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
import { AttachmentsSection, ConfirmDialog } from '@/components/features';
import { useDeleteMaintenance, useMaintenance } from '@/hooks/useMaintenances';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useToast } from '@/hooks/useToast';
import { formatCurrency, formatDate, formatKm } from '@/lib/format';

export function MaintenanceDetailPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const maintenanceId = Number(id);
  const navigate = useNavigate();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const { data, isLoading, error } = useMaintenance(maintenanceId);
  const deleteMutation = useDeleteMaintenance(data?.vehicleId ?? 0);
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
        title={t(`maintenance.type_options.${data.type}`)}
        description={data.description}
        onBack={() => navigate(`/maintenance?vehicleId=${data.vehicleId}`)}
        action={
          <>
            <Button variant="outline" size="icon" asChild aria-label={t('actions.edit')}>
              <Link to={`/maintenance/${data.id}/edit`}>
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
          <CardTitle>{t('maintenance.info')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap gap-2">
            <Badge variant={data.category === 'scheduled' ? 'success' : 'warning'}>
              {t(`maintenance.category_options.${data.category}`)}
            </Badge>
          </div>
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <dt className="text-muted-foreground">{t('maintenance.performed_at')}</dt>
              <dd className="font-medium">{formatDate(data.performedAt)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('maintenance.km')}</dt>
              <dd className="font-medium">{formatKm(data.km)}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('maintenance.cost')}</dt>
              <dd className="font-medium">{formatCurrency(data.cost)}</dd>
            </div>
            {data.workshop && (
              <div>
                <dt className="text-muted-foreground">{t('maintenance.workshop')}</dt>
                <dd className="font-medium">{data.workshop}</dd>
              </div>
            )}
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('attachment.title')}</CardTitle>
          <CardDescription>{t('attachment.description')}</CardDescription>
        </CardHeader>
        <CardContent>
          <AttachmentsSection entityType="maintenance" entityId={data.id} />
        </CardContent>
      </Card>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t('maintenance.delete.title')}
        description={t('maintenance.delete.description')}
        isPending={deleteMutation.isPending}
        confirmLabel={t('actions.delete')}
        onConfirm={() =>
          deleteMutation.mutate(maintenanceId, {
            onSuccess: () => {
              toast({ title: t('maintenance.deleted'), variant: 'success' });
              navigate(`/maintenance?vehicleId=${data.vehicleId}`, { replace: true });
            },
            onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
          })
        }
      />
    </div>
  );
}
