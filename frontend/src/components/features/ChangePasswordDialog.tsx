import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  FormField,
  Input,
} from '@/components/ui';
import { ApiError } from '@/api/client';
import { DialogFormActions } from './DialogFormActions';
import { useToast } from '@/hooks/useToast';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useFieldErrorMessage } from '@/hooks/useFieldErrorMessage';
import { useChangePassword } from '@/hooks/useAccount';
import { changePasswordSchema, type ChangePasswordFormData } from '@/schemas/account.schema';

export interface ChangePasswordDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const EMPTY: ChangePasswordFormData = {
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
};

export function ChangePasswordDialog({ open, onOpenChange }: ChangePasswordDialogProps) {
  const { t } = useTranslation();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();
  const mutation = useChangePassword();

  const form = useForm<ChangePasswordFormData>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: EMPTY,
  });

  useEffect(() => {
    if (open) form.reset(EMPTY);
  }, [open, form]);

  const errors = form.formState.errors;
  const tErr = useFieldErrorMessage();

  function submit(data: ChangePasswordFormData) {
    mutation.mutate(
      { currentPassword: data.currentPassword, newPassword: data.newPassword },
      {
        onSuccess: () => {
          toast({ title: t('account.password_updated'), variant: 'success' });
          onOpenChange(false);
        },
        onError: (e) => {
          if (e instanceof ApiError && e.title === 'auth.invalid_current_password') {
            form.setError('currentPassword', { message: 'auth.invalid_current_password' });
          } else {
            toast({ title: errorMessage(e), variant: 'error' });
          }
        },
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent mobileFullScreen>
        <DialogHeader>
          <DialogTitle>{t('account.change_password')}</DialogTitle>
          <DialogDescription>{t('account.change_password_description')}</DialogDescription>
        </DialogHeader>

        <form onSubmit={form.handleSubmit(submit)} className="space-y-4" noValidate>
          <FormField
            label={t('account.current_password')}
            error={tErr(errors.currentPassword?.message)}
            required
          >
            {(id) => (
              <Input
                id={id}
                type="password"
                autoComplete="current-password"
                invalid={!!errors.currentPassword}
                {...form.register('currentPassword')}
              />
            )}
          </FormField>

          <FormField
            label={t('account.new_password')}
            error={tErr(errors.newPassword?.message)}
            required
          >
            {(id) => (
              <Input
                id={id}
                type="password"
                autoComplete="new-password"
                invalid={!!errors.newPassword}
                {...form.register('newPassword')}
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

          <DialogFormActions
            isPending={mutation.isPending}
            onCancel={() => onOpenChange(false)}
            submitLabel={t('account.change_password')}
          />
        </form>
      </DialogContent>
    </Dialog>
  );
}
