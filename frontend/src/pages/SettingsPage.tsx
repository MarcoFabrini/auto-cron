import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Building2,
  Globe,
  KeyRound,
  LogOut,
  Mail,
  Moon,
  Pencil,
  Sun,
  User as UserIcon,
} from 'lucide-react';
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Separator,
  Text,
} from '@/components/ui';
import { PageHeader } from '@/components/layout';
import {
  ChangePasswordDialog,
  EditOrganizationDialog,
  EditProfileDialog,
  MembersCard,
  PushSettingsCard,
  UserAvatar,
} from '@/components/features';
import { useAuthStore } from '@/stores/useAuthStore';
import { useThemeStore, type Theme } from '@/stores/useThemeStore';
import { useLogout } from '@/hooks/useLogout';
import { useUpdateProfile } from '@/hooks/useAccount';
import { useActiveMembership } from '@/hooks/useActiveMembership';
import { useToast } from '@/hooks/useToast';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import i18n from '@/i18n';

type DialogKey = 'profile' | 'password' | 'org' | null;

/** Riga info: icona + etichetta + valore. */
function InfoRow({
  icon,
  label,
  value,
}: {
  icon: ReactNode;
  label: string;
  value: ReactNode;
}) {
  return (
    <div className="flex items-center gap-4 py-1">
      <div className="flex min-w-0 items-center gap-3">
        <span className="text-muted-foreground">{icon}</span>
        <div className="min-w-0">
          <p className="text-xs text-muted-foreground">{label}</p>
          <div className="truncate font-medium">{value}</div>
        </div>
      </div>
    </div>
  );
}

export function SettingsPage() {
  const { t } = useTranslation();
  const user = useAuthStore((s) => s.user);
  const theme = useThemeStore((s) => s.theme);
  const setTheme = useThemeStore((s) => s.setTheme);
  const logout = useLogout();
  const updateProfile = useUpdateProfile();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();

  const [dialog, setDialog] = useState<DialogKey>(null);

  /**
   * Lingua unificata: cambia subito la UI (i18next, ottimistico) e persiste il
   * locale sul profilo backend, così email/notifiche e UI restano allineate.
   * Se il salvataggio fallisce: rollback della UI + toast d'errore, altrimenti
   * il desync resterebbe silenzioso fino al prossimo reload di /me.
   */
  function changeLanguage(locale: 'it' | 'en') {
    if (!user) return;
    const previous = i18n.language.startsWith('en') ? 'en' : 'it';
    if (locale === previous) return;

    void i18n.changeLanguage(locale);
    updateProfile.mutate(
      {
        email: user.email,
        firstName: user.firstName,
        lastName: user.lastName,
        locale,
      },
      {
        onError: (e) => {
          void i18n.changeLanguage(previous);
          toast({ title: errorMessage(e), variant: 'error' });
        },
      },
    );
  }

  const membership = useActiveMembership();
  const canEditOrg = membership?.role === 'owner' || membership?.role === 'admin';

  return (
    <div className="space-y-6">
      <PageHeader title={t('nav.settings')} />

      {/* ── Profilo (info utente) ─────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between gap-2">
            <span className="flex items-center gap-2">
              <UserIcon className="size-4" />
              {t('settings.profile')}
            </span>
            <Button
              variant="outline"
              size="icon"
              onClick={() => setDialog('profile')}
              aria-label={t('actions.edit')}
            >
              <Pencil />
            </Button>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {user && (
            <>
              <div className="flex items-center gap-4">
                <UserAvatar user={user} />
                <div className="min-w-0">
                  <p className="truncate text-base font-semibold">
                    {user.firstName} {user.lastName}
                  </p>
                  <p className="truncate text-sm text-muted-foreground">{user.email}</p>
                </div>
              </div>

              <Separator />

              <InfoRow
                icon={<UserIcon className="size-4" />}
                label={t('account.name')}
                value={`${user.firstName} ${user.lastName}`}
              />
              <InfoRow
                icon={<Mail className="size-4" />}
                label={t('account.email')}
                value={user.email}
              />
            </>
          )}
        </CardContent>
      </Card>

      {/* ── Sicurezza ─────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between gap-2">
            <span className="flex items-center gap-2">
              <KeyRound className="size-4" />
              {t('settings.security')}
            </span>
            <Button
              variant="outline"
              size="icon"
              onClick={() => setDialog('password')}
              aria-label={t('account.change_password')}
            >
              <Pencil />
            </Button>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <InfoRow
            icon={<KeyRound className="size-4" />}
            label={t('account.password')}
            value="••••••••"
          />
        </CardContent>
      </Card>

      {/* ── Organizzazione ────────────────────────────────────── */}
      {membership && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center justify-between gap-2">
              <span className="flex items-center gap-2">
                <Building2 className="size-4" />
                {t('settings.organization')}
              </span>
              {canEditOrg && (
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => setDialog('org')}
                  aria-label={t('actions.edit')}
                >
                  <Pencil />
                </Button>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            <InfoRow
              icon={<Building2 className="size-4" />}
              label={t('account.org_name')}
              value={
                <span className="flex items-center gap-2">
                  {membership.organization.name}
                  <Badge variant="secondary">{t(`settings.role.${membership.role}`)}</Badge>
                </span>
              }
            />
          </CardContent>
        </Card>
      )}

      {/* ── Membri organizzazione (owner/admin) ───────────────── */}
      {membership && canEditOrg && user && (
        <MembersCard organizationId={membership.organization.id} currentUserId={user.id} />
      )}

      {/* ── Preferenze app ────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>{t('settings.preferences')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <Globe className="size-4 text-muted-foreground" />
              <Text>{t('settings.language')}</Text>
            </div>
            <Select
              value={i18n.language.startsWith('en') ? 'en' : 'it'}
              onValueChange={(v) => changeLanguage(v as 'it' | 'en')}
              disabled={updateProfile.isPending}
            >
              <SelectTrigger className="w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="it">Italiano</SelectItem>
                <SelectItem value="en">English</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <Separator />

          <div className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              {theme === 'dark' ? (
                <Moon className="size-4 text-muted-foreground" />
              ) : (
                <Sun className="size-4 text-muted-foreground" />
              )}
              <Text>{t('settings.theme')}</Text>
            </div>
            <Select value={theme} onValueChange={(v) => setTheme(v as Theme)}>
              <SelectTrigger className="w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="light">{t('settings.theme_light')}</SelectItem>
                <SelectItem value="dark">{t('settings.theme_dark')}</SelectItem>
                <SelectItem value="system">{t('settings.theme_system')}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* ── Notifiche push: sottoscrizione dispositivo (tutti) + VAPID (solo owner) ── */}
      <PushSettingsCard />

      {/* ── Logout ────────────────────────────────────────────── */}
      <Button variant="destructive" fullWidth onClick={() => void logout()}>
        <LogOut />
        {t('auth.logout')}
      </Button>

      {/* ── Modali ────────────────────────────────────────────── */}
      {user && (
        <>
          <EditProfileDialog
            open={dialog === 'profile'}
            onOpenChange={(o) => setDialog(o ? 'profile' : null)}
            user={user}
          />
          <ChangePasswordDialog
            open={dialog === 'password'}
            onOpenChange={(o) => setDialog(o ? 'password' : null)}
          />
          {membership && (
            <EditOrganizationDialog
              open={dialog === 'org'}
              onOpenChange={(o) => setDialog(o ? 'org' : null)}
              organizationId={membership.organization.id}
              currentName={membership.organization.name}
            />
          )}
        </>
      )}
    </div>
  );
}
