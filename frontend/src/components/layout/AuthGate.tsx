import type { ReactNode } from 'react';
import { Spinner } from '@/components/ui';
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
  const status = useAuthBootstrap();

  if (status === 'loading') {
    return (
      <div className="flex min-h-dvh items-center justify-center bg-background">
        <Spinner size="lg" />
      </div>
    );
  }

  return <>{children}</>;
}
