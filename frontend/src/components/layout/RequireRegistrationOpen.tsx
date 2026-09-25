import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { Spinner } from '@/components/ui';
import { useRegistrationOpen } from '@/hooks/useRegistrationOpen';
import { AuthLayout } from './AuthLayout';

/**
 * Route guard: la pagina di registrazione esiste solo a istanza vuota (primo
 * avvio). Se è chiusa — o lo stato non è verificabile — si torna al login:
 * nel dubbio la registrazione resta chiusa (il backend risponde comunque 403).
 */
export function RequireRegistrationOpen({ children }: { children: ReactNode }) {
  const { data, isPending } = useRegistrationOpen();

  if (isPending) {
    return (
      <AuthLayout>
        <div className="flex justify-center">
          <Spinner size="lg" />
        </div>
      </AuthLayout>
    );
  }
  if (!data?.open) return <Navigate to="/login" replace />;
  return <>{children}</>;
}
