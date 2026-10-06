import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Alert, Button, FormField, Input, Spinner, Text } from '@/components/ui';
import { AuthLayout } from '@/components/layout';
import { AuthBrand, AuthLink } from '@/components/features';
import { useLogin } from '@/hooks/useLogin';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useFieldErrorMessage } from '@/hooks/useFieldErrorMessage';
import { useRegistrationOpen } from '@/hooks/useRegistrationOpen';
import { safeNextPath } from '@/lib/safeNext';
import { loginSchema, type LoginFormData } from '@/schemas/auth.schema';

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

  const form = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });
  const errors = form.formState.errors;
  const tErr = useFieldErrorMessage();

  // Redirect post-login: solo path interni (evita open-redirect).
  const next = params.get('next');
  const dest = safeNextPath(next);

  // Lo schema pulisce l'email dagli spazi e blocca i campi vuoti prima di chiamare il server.
  function onSubmit({ email, password }: LoginFormData) {
    mutate({ email, password }, { onSuccess: () => navigate(dest, { replace: true }) });
  }

  return (
    <AuthLayout>
      <AuthBrand subtitleKey="auth.login.title" />

      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
        <FormField label={t('auth.login.email')} error={tErr(errors.email?.message)} required>
          {(id) => (
            <Input
              id={id}
              type="email"
              autoComplete="email"
              autoCapitalize="none"
              inputMode="email"
              invalid={!!errors.email}
              {...form.register('email')}
            />
          )}
        </FormField>

        <FormField label={t('auth.login.password')} error={tErr(errors.password?.message)} required>
          {(id) => (
            <Input
              id={id}
              type="password"
              autoComplete="current-password"
              invalid={!!errors.password}
              {...form.register('password')}
            />
          )}
        </FormField>

        <div className="text-right">
          <AuthLink to="/forgot-password" className="text-sm">
            {t('auth.forgot.link')}
          </AuthLink>
        </div>

        {error && <Alert variant="error">{errorMessage(error)}</Alert>}

        <Button type="submit" fullWidth disabled={isPending}>
          {isPending ? <Spinner size="sm" /> : t('auth.login.submit')}
        </Button>
      </form>

      {registration?.open && (
        <Text variant="muted" className="text-center">
          {t('auth.login.no_account')}{' '}
          <AuthLink to="/register">{t('auth.login.register')}</AuthLink>
        </Text>
      )}
    </AuthLayout>
  );
}
