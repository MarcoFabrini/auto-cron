import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Spinner } from '@/components/ui';
import { useAuthBootstrap } from '@/hooks/useAuthBootstrap';
import { useThemeEffect } from '@/hooks/useThemeEffect';

/**
 * AuthGate — wrapper top-level che esegue bootstrap auth + applica theme.
 *
 * Mostra splash spinner finché lo stato è 'loading' (tentativo refresh
 * in corso). Poi renderizza i children (router). Evita il flash di
 * redirect-to-login su page reload con sessione valida.
 */
export interface AuthGateProps {
  children: ReactNode;
}

export function AuthGate({ children }: AuthGateProps) {
  useThemeEffect();
  const { t } = useTranslation();
  const { status, retry } = useAuthBootstrap();

  if (status === 'loading') {
    return (
      <div className="flex min-h-dvh items-center justify-center bg-background">
        <Spinner size="lg" />
      </div>
    );
  }

  if (status === 'unreachable') {
    return (
      <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-background p-6 text-center">
        <p className="text-base font-medium">{t('authGate.unreachable.title')}</p>
        <p className="max-w-sm text-sm text-muted-foreground">{t('authGate.unreachable.description')}</p>
        <Button onClick={retry}>{t('authGate.unreachable.retry')}</Button>
      </div>
    );
  }

  return <>{children}</>;
}
