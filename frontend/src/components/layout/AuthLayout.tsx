import type { ReactNode } from 'react';

/**
 * AuthLayout — wrapper centrato per pagine non autenticate (login, register, password reset).
 *
 * Card max-w-sm centrata, padding safe-area iOS.
 */
export interface AuthLayoutProps {
  children: ReactNode;
}

export function AuthLayout({ children }: AuthLayoutProps) {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-background p-4 pt-safe">
      <div className="w-full max-w-sm space-y-6">{children}</div>
    </div>
  );
}
