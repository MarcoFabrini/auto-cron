import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { Alert, Button, FormField, Heading, Input, Spinner, Text } from '@/components/ui';
import { AuthLayout } from '@/components/layout';
import { AuthBrand } from '@/components/features';
import { useResetPassword } from '@/hooks/useAccount';
import { useToast } from '@/hooks/useToast';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { resetPasswordSchema, type ResetPasswordFormData } from '@/schemas/auth.schema';

export function ResetPasswordPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const mutation = useResetPassword();

  const form = useForm<ResetPasswordFormData>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: '', confirmPassword: '' },
  });

  const errors = form.formState.errors;
  const tErr = (k?: string) => (k ? t(`errors.${k}`, { defaultValue: t(k, { defaultValue: k }) }) : undefined);

  function submit(data: ResetPasswordFormData) {
    mutation.mutate(
      { token, password: data.password },
      {
        onSuccess: () => {
          toast({ title: t('auth.reset.success'), variant: 'success' });
          navigate('/login', { replace: true });
        },
      },
    );
  }

  if (!token) {
    return (
      <AuthLayout>
        <div className="flex flex-col items-center gap-4 text-center">
          <Heading level={2}>{t('auth.reset.invalid_title')}</Heading>
          <Text variant="muted">{t('auth.reset.invalid_description')}</Text>
          <Button asChild variant="outline" className="mt-2">
            <Link to="/forgot-password">{t('auth.forgot.link')}</Link>
          </Button>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout>
      <AuthBrand subtitleKey="auth.reset.title" />

      <Text variant="muted" className="text-center">
        {t('auth.reset.description')}
      </Text>

      <form onSubmit={form.handleSubmit(submit)} className="space-y-4" noValidate>
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

        {mutation.isError && <Alert variant="error">{errorMessage(mutation.error)}</Alert>}

        <Button type="submit" fullWidth disabled={mutation.isPending}>
          {mutation.isPending ? <Spinner size="sm" /> : t('auth.reset.submit')}
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
