import { forwardRef, type InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Input atom — wrapper consistent su `<input>`.
 *
 * Touch target min 44px, font-size 16px su mobile per evitare auto-zoom iOS.
 * Stati: default, invalid (border destructive), disabled.
 *
 * @example
 * <Input type="email" autoComplete="email" />
 * <Input type="password" autoComplete="current-password" />
 */
export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  /** Aggiunge style invalid (border rosso) — usato da FormField */
  invalid?: boolean;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ className, type = 'text', invalid, ...props }, ref) => {
    return (
      <input
        ref={ref}
        type={type}
        aria-invalid={invalid || undefined}
        className={cn(
          'flex min-h-touch w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm transition-colors',
          'placeholder:text-muted-foreground',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
          'disabled:cursor-not-allowed disabled:opacity-50',
          'file:border-0 file:bg-transparent file:text-sm file:font-medium',
          invalid && 'border-destructive focus-visible:ring-destructive',
          className,
        )}
        {...props}
      />
    );
  },
);
Input.displayName = 'Input';
