import { useTranslation } from 'react-i18next';
import { useAuthStore } from '@/stores/useAuthStore';
import { useResendVerification } from '@/hooks/useAccount';
import { useToast } from '@/hooks/useToast';

/**
 * Banner non bloccante mostrato agli utenti con email non ancora verificata.
 * Permette di rinviare l'email di verifica. Sparisce una volta verificata.
 */
export function EmailVerificationBanner() {
  const { t } = useTranslation();
  const user = useAuthStore((s) => s.user);
  const { toast } = useToast();
  const { mutate, isPending } = useResendVerification();

  if (!user || user.emailVerified) return null;

  return (
    <div className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
      <span>{t('auth.verify.banner')}</span>
      <button
        type="button"
        disabled={isPending}
        onClick={() =>
          mutate(undefined, {
            onSuccess: () => toast({ title: t('auth.verify.resent'), variant: 'success' }),
          })
        }
        className="font-medium underline underline-offset-2 disabled:opacity-50"
      >
        {t('auth.verify.resend')}
      </button>
    </div>
  );
}
