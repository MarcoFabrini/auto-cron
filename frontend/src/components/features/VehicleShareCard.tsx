import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronsUpDown, Share2, Trash2 } from 'lucide-react';
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuTrigger,
  Spinner,
  Text,
} from '@/components/ui';
import { ConfirmDialog } from './ConfirmDialog';
import { useToast } from '@/hooks/useToast';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import {
  useCreateShare,
  useRevokeShare,
  useShareCandidates,
  useVehicleShares,
  type ShareCandidate,
  type VehicleShare,
} from '@/hooks/useVehicleShares';

export interface VehicleShareCardProps {
  vehicleId: number;
  currentUserId: number;
}

const fullName = (p: { firstName: string; lastName: string }) => `${p.firstName} ${p.lastName}`;

/**
 * Condivisione di un veicolo (visibile a chi può gestirlo: proprietario o org
 * owner/admin). Mostra il proprietario e le condivisioni, e permette di
 * condividere in SOLA LETTURA con uno o più membri dell'organizzazione scelti
 * per nome e cognome da un elenco (nessuna email da digitare). Lo share del
 * proprietario non è revocabile.
 */
export function VehicleShareCard({ vehicleId, currentUserId }: VehicleShareCardProps) {
  const { t } = useTranslation();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const sharesQuery = useVehicleShares(vehicleId);
  const candidatesQuery = useShareCandidates(vehicleId);
  const create = useCreateShare(vehicleId);
  const revoke = useRevokeShare(vehicleId);

  const [toRevoke, setToRevoke] = useState<VehicleShare | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);

  const candidates = candidatesQuery.data ?? [];
  // Derivato, non duplicato in uno state: dopo un refetch scompaiono le persone
  // che non sono più selezionabili (es. appena condivise da un altro dispositivo).
  const selected: ShareCandidate[] = candidates.filter((c) => selectedIds.includes(c.id));

  function toggle(id: number) {
    setSelectedIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]));
  }

  function submit() {
    if (selected.length === 0) return;
    create.mutate(
      { userIds: selected.map((c) => c.id) },
      {
        onSuccess: () => {
          toast({ title: t('vehicle.share.shared'), variant: 'success' });
          setSelectedIds([]);
        },
        onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
      },
    );
  }

  function confirmRevoke() {
    if (!toRevoke) return;
    revoke.mutate(toRevoke.id, {
      onSuccess: () => toast({ title: t('vehicle.share.revoked'), variant: 'success' }),
      onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
    });
    setToRevoke(null);
  }

  const triggerLabel =
    selected.length === 0
      ? t('vehicle.share.select_people')
      : selected.length <= 2
        ? selected.map(fullName).join(', ')
        : t('vehicle.share.selected_count', { count: selected.length });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Share2 className="size-4" />
          {t('vehicle.share.title')}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <Text variant="muted" className="text-sm">
          {t('vehicle.share.description')}
        </Text>

        {sharesQuery.isLoading && <Spinner size="sm" />}

        {sharesQuery.data &&
          (sharesQuery.data.length === 0 ? (
            <Text variant="muted" className="text-sm">
              {t('vehicle.share.empty')}
            </Text>
          ) : (
            <ul className="space-y-2">
              {sharesQuery.data.map((s) => {
                // Lo share `admin` è il proprietario del veicolo: non revocabile.
                const revocable = s.role !== 'admin' && s.user.id !== currentUserId;
                return (
                  <li
                    key={s.id}
                    className="flex items-center justify-between gap-3 rounded-lg border p-3"
                  >
                    <p className="min-w-0 truncate font-medium">
                      {fullName(s.user)}
                      {s.user.id === currentUserId && (
                        <span className="text-muted-foreground"> ({t('vehicle.share.you')})</span>
                      )}
                    </p>
                    <div className="flex shrink-0 items-center gap-2">
                      <Badge variant="secondary">{t(`vehicle.share.role.${s.role}`)}</Badge>
                      {revocable && (
                        <Button
                          variant="ghost"
                          size="icon"
                          aria-label={t('vehicle.share.revoke')}
                          onClick={() => setToRevoke(s)}
                        >
                          <Trash2 className="text-destructive" />
                        </Button>
                      )}
                    </div>
                  </li>
                );
              })}
            </ul>
          ))}

        <div className="space-y-3 border-t pt-4">
          <p className="text-sm font-medium">{t('vehicle.share.invite_title')}</p>

          {candidatesQuery.isLoading && <Spinner size="sm" />}

          {candidatesQuery.data && candidates.length === 0 && (
            <Text variant="muted" className="text-sm">
              {t('vehicle.share.no_candidates')}
            </Text>
          )}

          {candidates.length > 0 && (
            <>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button type="button" variant="outline" className="w-full justify-between">
                    <span className="truncate">{triggerLabel}</span>
                    <ChevronsUpDown className="opacity-50" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                  align="start"
                  className="max-h-72 w-[var(--radix-dropdown-menu-trigger-width)] overflow-y-auto"
                >
                  <DropdownMenuLabel>{t('vehicle.share.select_people')}</DropdownMenuLabel>
                  {candidates.map((c) => (
                    <DropdownMenuCheckboxItem
                      key={c.id}
                      checked={selectedIds.includes(c.id)}
                      onCheckedChange={() => toggle(c.id)}
                      // Multi-selezione: il menu resta aperto dopo ogni scelta.
                      onSelect={(e) => e.preventDefault()}
                    >
                      {fullName(c)}
                    </DropdownMenuCheckboxItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>

              <Button type="button" onClick={submit} disabled={selected.length === 0 || create.isPending}>
                {create.isPending ? (
                  <Spinner size="sm" />
                ) : selected.length > 1 ? (
                  t('vehicle.share.submit_many', { count: selected.length })
                ) : (
                  t('vehicle.share.invite_submit')
                )}
              </Button>
            </>
          )}
        </div>
      </CardContent>

      <ConfirmDialog
        open={toRevoke !== null}
        onOpenChange={(o) => !o && setToRevoke(null)}
        title={t('vehicle.share.revoke_title')}
        description={
          toRevoke ? t('vehicle.share.revoke_description', { name: fullName(toRevoke.user) }) : undefined
        }
        confirmLabel={t('vehicle.share.revoke')}
        confirmVariant="destructive"
        onConfirm={confirmRevoke}
      />
    </Card>
  );
}
