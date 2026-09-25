import { Link, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Alert, Button, FormField, Input, Spinner, Text } from '@/components/ui';
import { AuthLayout } from '@/components/layout';
import { AuthBrand } from '@/components/features';
import { useRegister } from '@/hooks/useRegister';
import { ApiError } from '@/api/client';
import { registerSchema, type RegisterFormData } from '@/schemas/auth.schema';

/**
 * RegisterPage — RHF + Zod. Conferma password client-side, errori backend
 * (es. email già usata) mappati ai campi.
 */
export function RegisterPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { mutate, isPending, error } = useRegister();

  const form = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      firstName: '',
      lastName: '',
      email: '',
      password: '',
      confirmPassword: '',
    },
  });

  const errors = form.formState.errors;
  const tErr = (k?: string) => (k ? t(`errors.${k}`, { defaultValue: t(k, { defaultValue: k }) }) : undefined);

  function submit(data: RegisterFormData) {
    mutate(
      {
        email: data.email,
        password: data.password,
        firstName: data.firstName,
        lastName: data.lastName,
      },
      {
        onSuccess: () => navigate('/', { replace: true }),
        onError: (e) => {
          if (e instanceof ApiError && e.title === 'auth.email_taken') {
            form.setError('email', { message: 'auth.email_taken' });
          }
        },
      },
    );
  }

  const showGlobalError =
    error instanceof ApiError && error.title !== 'auth.email_taken' && error.title !== 'validation_failed';

  return (
    <AuthLayout>
      <AuthBrand subtitleKey="auth.register.title" />
      <Text variant="muted" className="text-center">
        {t('auth.register.first_user_hint')}
      </Text>

      <form onSubmit={form.handleSubmit(submit)} className="space-y-4" noValidate>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <FormField label={t('auth.register.first_name')} error={tErr(errors.firstName?.message)} required>
            {(id) => (
              <Input id={id} autoComplete="given-name" invalid={!!errors.firstName} {...form.register('firstName')} />
            )}
          </FormField>
          <FormField label={t('auth.register.last_name')} error={tErr(errors.lastName?.message)} required>
            {(id) => (
              <Input id={id} autoComplete="family-name" invalid={!!errors.lastName} {...form.register('lastName')} />
            )}
          </FormField>
        </div>

        <FormField label={t('auth.register.email')} error={tErr(errors.email?.message)} required>
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

        <FormField label={t('auth.register.password')} error={tErr(errors.password?.message)} required>
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

        <FormField
          label={t('account.confirm_password')}
          error={tErr(errors.confirmPassword?.message)}
          required
        >
          {(id) => (
            <Input
              id={id}
              type="password"
              autoComplete="new-password"
              invalid={!!errors.confirmPassword}
              {...form.register('confirmPassword')}
            />
          )}
        </FormField>

        {showGlobalError && (
          <Alert variant="error">
            {t(`errors.${error.title}`, { defaultValue: error.detail ?? error.title })}
          </Alert>
        )}

        <Button type="submit" fullWidth disabled={isPending}>
          {isPending ? <Spinner size="sm" /> : t('auth.register.submit')}
        </Button>
      </form>

      <Text variant="muted" className="text-center">
        {t('auth.register.have_account')}{' '}
        <Link to="/login" className="font-medium text-primary hover:underline">
          {t('auth.register.login')}
        </Link>
      </Text>
    </AuthLayout>
  );
}
