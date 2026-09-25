import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Clock, Trash2, UserPlus, X } from 'lucide-react';
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  FormField,
  Input,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Spinner,
} from '@/components/ui';
import { ConfirmDialog } from './ConfirmDialog';
import { useToast } from '@/hooks/useToast';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useFieldErrorMessage } from '@/hooks/useFieldErrorMessage';
import {
  useInvitations,
  useInviteMember,
  useMembers,
  useRemoveMember,
  useRevokeInvitation,
  type OrgMember,
} from '@/hooks/useOrganizationMembers';
import { inviteMemberSchema, type InviteMemberFormData } from '@/schemas/account.schema';

export interface MembersCardProps {
  organizationId: number;
  currentUserId: number;
}

/**
 * Gestione membri organizzazione (owner/admin): membri attivi + inviti pendenti
 * + form per invitare un'email (anche non registrata: l'account si crea in
 * fase di accettazione).
 */
export function MembersCard({ organizationId, currentUserId }: MembersCardProps) {
  const { t } = useTranslation();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();
  const tErr = useFieldErrorMessage();

  const membersQuery = useMembers(organizationId);
  const invitationsQuery = useInvitations(organizationId);
  const invite = useInviteMember(organizationId);
  const revoke = useRevokeInvitation(organizationId);
  const remove = useRemoveMember(organizationId);

  const [toRemove, setToRemove] = useState<OrgMember | null>(null);

  const form = useForm<InviteMemberFormData>({
    resolver: zodResolver(inviteMemberSchema),
    defaultValues: { email: '', role: 'member' },
  });
  const errors = form.formState.errors;

  function submit(data: InviteMemberFormData) {
    invite.mutate(data, {
      onSuccess: () => {
        toast({ title: t('settings.members.invited'), variant: 'success' });
        form.reset({ email: '', role: 'member' });
      },
      onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
    });
  }

  function confirmRemove() {
    if (!toRemove) return;
    remove.mutate(toRemove.id, {
      onSuccess: () => toast({ title: t('settings.members.removed'), variant: 'success' }),
      onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
    });
    setToRemove(null);
  }

  function revokeInvitation(id: number) {
    revoke.mutate(id, {
      onSuccess: () => toast({ title: t('settings.members.invite_revoked'), variant: 'success' }),
      onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <UserPlus className="size-4" />
          {t('settings.members.title')}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {membersQuery.isLoading && <Spinner size="sm" />}

        {membersQuery.data && (
          <ul className="space-y-2">
            {membersQuery.data.map((m) => (
              <li
                key={m.id}
                className="flex items-center justify-between gap-3 rounded-lg border p-3"
              >
                <div className="min-w-0">
                  <p className="truncate font-medium">
                    {m.user.firstName} {m.user.lastName}
                    {m.user.id === currentUserId && (
                      <span className="text-muted-foreground"> ({t('settings.members.you')})</span>
                    )}
                  </p>
                  <p className="truncate text-xs text-muted-foreground">{m.user.email}</p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  <Badge variant="secondary">{t(`settings.role.${m.role}`)}</Badge>
                  {m.role !== 'owner' && m.user.id !== currentUserId && (
                    <Button
                      variant="ghost"
                      size="icon"
                      aria-label={t('settings.members.remove')}
                      onClick={() => setToRemove(m)}
                    >
                      <Trash2 className="text-destructive" />
                    </Button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}

        {invitationsQuery.data && invitationsQuery.data.length > 0 && (
          <div className="space-y-2">
            <p className="text-sm font-medium text-muted-foreground">
              {t('settings.members.pending_title')}
            </p>
            <ul className="space-y-2">
              {invitationsQuery.data.map((inv) => (
                <li
                  key={inv.id}
                  className="flex items-center justify-between gap-3 rounded-lg border border-dashed p-3"
                >
                  <p className="min-w-0 truncate text-sm">{inv.email}</p>
                  <div className="flex shrink-0 items-center gap-2">
                    <Badge variant="secondary">{t(`settings.role.${inv.role}`)}</Badge>
                    <Badge variant="outline" className="gap-1">
                      <Clock className="size-3" />
                      {t('settings.members.pending')}
                    </Badge>
                    <Button
                      variant="ghost"
                      size="icon"
                      aria-label={t('settings.members.revoke')}
                      disabled={revoke.isPending}
                      onClick={() => revokeInvitation(inv.id)}
                    >
                      <X className="text-destructive" />
                    </Button>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        )}

        <form onSubmit={form.handleSubmit(submit)} className="space-y-3 border-t pt-4">
          <p className="text-sm font-medium">{t('settings.members.invite_title')}</p>

          <FormField label={t('settings.members.email')} error={tErr(errors.email?.message)} required>
            {(id) => (
              <Input
                id={id}
                type="email"
                autoComplete="off"
                autoCapitalize="none"
                inputMode="email"
                invalid={!!errors.email}
                {...form.register('email')}
              />
            )}
          </FormField>

          <FormField label={t('settings.members.role')}>
            {(id) => (
              <Select
                value={form.watch('role')}
                onValueChange={(v) => form.setValue('role', v as 'member' | 'admin')}
              >
                <SelectTrigger id={id}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="member">{t('settings.role.member')}</SelectItem>
                  <SelectItem value="admin">{t('settings.role.admin')}</SelectItem>
                </SelectContent>
              </Select>
            )}
          </FormField>

          <Button type="submit" disabled={invite.isPending}>
            {invite.isPending ? <Spinner size="sm" /> : t('settings.members.invite_submit')}
          </Button>
        </form>
      </CardContent>

      <ConfirmDialog
        open={toRemove !== null}
        onOpenChange={(o) => !o && setToRemove(null)}
        title={t('settings.members.remove_title')}
        description={
          toRemove ? t('settings.members.remove_description', { email: toRemove.user.email }) : undefined
        }
        confirmLabel={t('settings.members.remove')}
        confirmVariant="destructive"
        onConfirm={confirmRemove}
      />
    </Card>
  );
}
