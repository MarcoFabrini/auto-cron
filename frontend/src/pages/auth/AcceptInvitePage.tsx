import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Alert, Button, FormField, Heading, Input, Spinner, Text } from '@/components/ui';
import { AuthLayout } from '@/components/layout';
import { AuthBrand, AuthLink } from '@/components/features';
import { useAuthStore } from '@/stores/useAuthStore';
import { useLogout } from '@/hooks/useLogout';
import {
  useAcceptInvitation,
  useInvitationPreview,
  useRegisterInvited,
} from '@/hooks/useAccount';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useFieldErrorMessage } from '@/hooks/useFieldErrorMessage';
import { useToast } from '@/hooks/useToast';
import {
  acceptInviteRegisterSchema,
  type AcceptInviteRegisterFormData,
} from '@/schemas/account.schema';

/**
 * Pagina dal link nell'email di invito (`/accept-invite?token=...`).
 * - account esistente + loggato con quell'email → accetta;
 * - account esistente + non loggato → manda al login;
 * - email senza account → form di registrazione (email bloccata) + accetta.
 */
export function AcceptInvitePage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const logout = useLogout();
  const { toast } = useToast();
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const status = useAuthStore((s) => s.status);
  const currentEmail = useAuthStore((s) => s.user?.email);
  const errorMessage = useApiErrorMessage();
  const tErr = useFieldErrorMessage();

  const preview = useInvitationPreview(token);
  const accept = useAcceptInvitation();
  const register = useRegisterInvited();
  // Blocca il doppio invio dell'effetto (StrictMode): l'invito si consuma alla prima accettazione.
  const accepted = useRef(false);
  // Nome dell'organizzazione salvato al momento dell'accettazione: poi l'anteprima non è più
  // leggibile (invito consumato) e la schermata di successo deve comunque nominarla.
  const [joinedOrg, setJoinedOrg] = useState('');

  async function signOutAndContinue() {
    await logout();
    // Da non autenticati lo stesso link propone registrazione/accesso con l'email dell'invito.
    navigate(`/accept-invite?token=${encodeURIComponent(token)}`, { replace: true });
  }

  const data = preview.data;
  const sameEmail =
    !!currentEmail && !!data && currentEmail.toLowerCase() === data.email.toLowerCase();

  function acceptInvitation() {
    const org = data?.organizationName;
    accept.mutate({ token }, { onSuccess: () => setJoinedOrg(org ?? '') });
  }

  // Utente esistente loggato con l'email giusta → accetta automaticamente.
  useEffect(() => {
    if (status === 'authenticated' && sameEmail && !accepted.current) {
      accepted.current = true;
      acceptInvitation();
    }
    // `acceptInvitation` è ricreata a ogni render e dipende da `data`/`accept`, già coperti da `sameEmail`/`accept`.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status, sameEmail, token, accept]);

  const form = useForm<AcceptInviteRegisterFormData>({
    resolver: zodResolver(acceptInviteRegisterSchema),
    defaultValues: { firstName: '', lastName: '', password: '' },
  });
  const errors = form.formState.errors;

  function submitRegister(values: AcceptInviteRegisterFormData) {
    register.mutate(
      { token, ...values },
      {
        onSuccess: () => {
          toast({ title: t('auth.invite.success_title'), variant: 'success' });
          navigate('/', { replace: true });
        },
        onError: (e) => toast({ title: errorMessage(e), variant: 'error' }),
      },
    );
  }

  if (!token) {
    return (
      <Centered>
        <Heading level={2}>{t('auth.invite.invalid_title')}</Heading>
        <Text variant="muted">{t('auth.invite.invalid_description')}</Text>
        <Button asChild variant="outline" className="mt-2">
          <Link to="/">{t('auth.invite.continue')}</Link>
        </Button>
      </Centered>
    );
  }

  // L'accettazione svuota la cache e l'invito è ormai consumato: l'anteprima può solo fallire, quindi
  // l'esito positivo ha la precedenza su qualunque stato della query.
  if (status === 'authenticated' && accept.isSuccess) {
    return (
      <Centered>
        <Heading level={2}>{t('auth.invite.success_title')}</Heading>
        <Text variant="muted">
          {joinedOrg ? t('auth.invite.joined', { org: joinedOrg }) : t('auth.invite.success_description')}
        </Text>
        <Button asChild className="mt-2">
          <Link to="/">{t('auth.invite.continue')}</Link>
        </Button>
      </Centered>
    );
  }

  if (status === 'loading' || preview.isLoading) {
    return (
      <Centered>
        <Spinner />
      </Centered>
    );
  }

  if (preview.isError || !data) {
    return (
      <Centered>
        <Heading level={2}>{t('auth.invite.invalid_title')}</Heading>
        <Text variant="muted">{t('auth.invite.invalid_description')}</Text>
        <Button asChild variant="outline" className="mt-2">
          <Link to="/">{t('auth.invite.continue')}</Link>
        </Button>
      </Centered>
    );
  }

  // Loggato con un'email diversa da quella invitata.
  if (status === 'authenticated' && !sameEmail) {
    return (
      <Centered>
        <Heading level={2}>{t('auth.invite.title')}</Heading>
        <Text variant="muted">{t('auth.invite.email_mismatch', { email: data.email })}</Text>
        {/* L'invito è per un'altra email: si esce e si riapre lo stesso link per accettare/registrarsi. */}
        <Button className="mt-2" onClick={() => void signOutAndContinue()}>
          {t('auth.invite.switch_account')}
        </Button>
        <Button asChild variant="outline">
          <Link to="/settings">{t('auth.invite.continue')}</Link>
        </Button>
      </Centered>
    );
  }

  // Loggato con l'email giusta → esito dell'accettazione automatica.
  if (status === 'authenticated') {
    return (
      <Centered>
        {!accept.isError && (
          <>
            <Spinner />
            <Text variant="muted">{t('auth.invite.accepting')}</Text>
          </>
        )}
        {accept.isError && (
          <>
            <Heading level={2}>{t('auth.invite.error_title')}</Heading>
            <Alert variant="error">{errorMessage(accept.error)}</Alert>
            <Button className="mt-2" onClick={acceptInvitation}>
              {t('auth.invite.retry')}
            </Button>
            <Button asChild variant="outline">
              <Link to="/">{t('auth.invite.continue')}</Link>
            </Button>
          </>
        )}
      </Centered>
    );
  }

  // Non loggato + l'email ha già un account → vai al login (e torna qui).
  if (data.accountExists) {
    const next = encodeURIComponent(`/accept-invite?token=${token}`);
    return (
      <Centered>
        <Heading level={2}>{t('auth.invite.title')}</Heading>
        <Text variant="muted">
          {t('auth.invite.login_required_for', { email: data.email, org: data.organizationName })}
        </Text>
        <Button asChild className="mt-2">
          <Link to={`/login?next=${next}`}>{t('auth.login.submit')}</Link>
        </Button>
      </Centered>
    );
  }

  // Non loggato + email senza account → registrazione contestuale.
  return (
    <AuthLayout>
      <AuthBrand subtitleKey="auth.invite.title" />
      <Text variant="muted" className="text-center">
        {t('auth.invite.register_intro', { email: data.email, org: data.organizationName })}
      </Text>

      <form onSubmit={form.handleSubmit(submitRegister)} className="space-y-4" noValidate>
        <FormField label={t('account.first_name')} error={tErr(errors.firstName?.message)} required>
          {(id) => <Input id={id} autoComplete="given-name" invalid={!!errors.firstName} {...form.register('firstName')} />}
        </FormField>
        <FormField label={t('account.last_name')} error={tErr(errors.lastName?.message)} required>
          {(id) => <Input id={id} autoComplete="family-name" invalid={!!errors.lastName} {...form.register('lastName')} />}
        </FormField>
        <FormField label={t('account.new_password')} error={tErr(errors.password?.message)} required>
          {(id) => (
            <Input
              id={id}
              type="password"
              autoComplete="new-password"
              invalid={!!errors.password}
              {...form.register('password')}
            />
          )}
        </FormField>

        {register.isError && <Alert variant="error">{errorMessage(register.error)}</Alert>}

        <Button type="submit" fullWidth disabled={register.isPending}>
          {register.isPending ? <Spinner size="sm" /> : t('auth.invite.register_submit')}
        </Button>
      </form>

      <Text variant="muted" className="text-center">
        {t('auth.invite.have_account')}{' '}
        <AuthLink to={`/login?next=${encodeURIComponent(`/accept-invite?token=${token}`)}`}>
          {t('auth.login.submit')}
        </AuthLink>
      </Text>
    </AuthLayout>
  );
}

function Centered({ children }: { children: ReactNode }) {
  return (
    <AuthLayout>
      <div className="flex flex-col items-center gap-4 text-center">{children}</div>
    </AuthLayout>
  );
}
