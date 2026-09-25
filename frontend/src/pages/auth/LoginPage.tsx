import { useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Alert, Button, FormField, Input, Spinner, Text } from '@/components/ui';
import { AuthLayout } from '@/components/layout';
import { AuthBrand } from '@/components/features';
import { useLogin } from '@/hooks/useLogin';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useRegistrationOpen } from '@/hooks/useRegistrationOpen';

/**
 * LoginPage — composta da AuthLayout + AuthBrand + form di FormField/Input/Button.
 * Logica delegata a useLogin hook. Errori formattati da useApiErrorMessage.
 */
export function LoginPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const errorMessage = useApiErrorMessage();
  const { mutate, isPending, error } = useLogin();
  const [params] = useSearchParams();
  // Link di registrazione solo a istanza vuota (primo avvio): poi si entra su invito.
  const { data: registration } = useRegistrationOpen();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  // Redirect post-login: solo path interni (evita open-redirect).
  const next = params.get('next');
  const dest = next && next.startsWith('/') ? next : '/';

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    mutate({ email, password }, { onSuccess: () => navigate(dest, { replace: true }) });
  }

  return (
    <AuthLayout>
      <AuthBrand subtitleKey="auth.login.title" />

      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <FormField label={t('auth.login.email')} required>
          {(id) => (
            <Input
              id={id}
              type="email"
              required
              autoComplete="email"
              autoCapitalize="none"
              inputMode="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          )}
        </FormField>

        <FormField label={t('auth.login.password')} required>
          {(id) => (
            <Input
              id={id}
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          )}
        </FormField>

        <div className="text-right">
          <Link
            to="/forgot-password"
            className="text-sm font-medium text-primary hover:underline"
          >
            {t('auth.forgot.link')}
          </Link>
        </div>

        {error && <Alert variant="error">{errorMessage(error)}</Alert>}

        <Button type="submit" fullWidth disabled={isPending}>
          {isPending ? <Spinner size="sm" /> : t('auth.login.submit')}
        </Button>
      </form>

      {registration?.open && (
        <Text variant="muted" className="text-center">
          {t('auth.login.no_account')}{' '}
          <Link to="/register" className="font-medium text-primary hover:underline">
            {t('auth.login.register')}
          </Link>
        </Text>
      )}
    </AuthLayout>
  );
}
