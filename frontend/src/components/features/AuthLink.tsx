import type { ComponentProps } from 'react';
import { Link } from 'react-router-dom';
import { cn } from '@/lib/utils';

/**
 * AuthLink feature — link testuale delle pagine di autenticazione ("Accedi", "Registrati",
 * "Password dimenticata?"), anche dentro una frase.
 *
 * Il bersaglio tocca i 44px (`min-h-touch`/`min-w-touch`) senza cambiare l'impaginazione: i margini
 * verticali negativi compensano l'altezza in più, quindi la riga resta alta come il testo.
 */
export function AuthLink({ className, ...props }: ComponentProps<typeof Link>) {
  return (
    <Link
      className={cn(
        '-my-3 inline-flex min-h-touch min-w-touch items-center justify-center font-medium text-primary hover:underline',
        className,
      )}
      {...props}
    />
  );
}
