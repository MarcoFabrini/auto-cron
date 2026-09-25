import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { MailCheck } from 'lucide-react';
import { Alert, Button, FormField, Heading, Input, Spinner, Text } from '@/components/ui';
import { AuthLayout } from '@/components/layout';
import { AuthBrand } from '@/components/features';
import { useForgotPassword } from '@/hooks/useAccount';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { forgotPasswordSchema, type ForgotPasswordFormData } from '@/schemas/auth.schema';

export function ForgotPasswordPage() {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const mutation = useForgotPassword();

  const form = useForm<ForgotPasswordFormData>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
  });

  const errors = form.formState.errors;
  const tErr = (k?: string) => (k ? t(`errors.${k}`, { defaultValue: t(k, { defaultValue: k }) }) : undefined);

  function submit(data: ForgotPasswordFormData) {
    mutation.mutate(data);
  }

  // Risposta sempre 200 (no enumeration): mostriamo conferma generica.
  if (mutation.isSuccess) {
    return (
      <AuthLayout>
        <div className="flex flex-col items-center gap-4 text-center">
          <div className="rounded-full bg-primary/10 p-3">
            <MailCheck className="size-7 text-primary" />
          </div>
          <Heading level={2}>{t('auth.forgot.sent_title')}</Heading>
          <Text variant="muted">{t('auth.forgot.sent_description')}</Text>
          <Button asChild variant="outline" className="mt-2">
            <Link to="/login">{t('auth.forgot.back_to_login')}</Link>
          </Button>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout>
      <AuthBrand subtitleKey="auth.forgot.title" />

      <Text variant="muted" className="text-center">
        {t('auth.forgot.description')}
      </Text>

      <form onSubmit={form.handleSubmit(submit)} className="space-y-4" noValidate>
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

        {mutation.isError && <Alert variant="error">{errorMessage(mutation.error)}</Alert>}

        <Button type="submit" fullWidth disabled={mutation.isPending}>
          {mutation.isPending ? <Spinner size="sm" /> : t('auth.forgot.submit')}
        </Button>
      </form>

      <Text variant="muted" className="text-center">
        <Link to="/login" className="font-medium text-primary hover:underline">
          {t('auth.forgot.back_to_login')}
        </Link>
      </Text>
    </AuthLayout>
  );
}
