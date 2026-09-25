import { useId, type ReactNode } from 'react';
import { Label } from './Label';
import { cn } from '@/lib/utils';

/**
 * FormField molecule — Label + child input + error/hint sotto.
 *
 * Genera id univoco automaticamente, lo passa come prop al child via render-prop.
 * Mantiene a11y consistente: aria-describedby per hint/error.
 *
 * @example
 * <FormField label="Email" error={errors.email?.message}>
 *   {(id) => <Input id={id} type="email" {...register('email')} />}
 * </FormField>
 *
 * <FormField label="Password" hint="Almeno 8 caratteri">
 *   {(id) => <Input id={id} type="password" />}
 * </FormField>
 */
export interface FormFieldProps {
  label: string;
  /** Render prop: riceve l'id generato e lo passa all'input child */
  children: (id: string) => ReactNode;
  /** Messaggio errore (mostrato in rosso sotto l'input) */
  error?: string;
  /** Hint sotto l'input (testo grigio). Non mostrato se error è presente. */
  hint?: string;
  /** Mostra asterisco rosso sul label */
  required?: boolean;
  className?: string;
}

export function FormField({ label, children, error, hint, required, className }: FormFieldProps) {
  const id = useId();
  const messageId = error || hint ? `${id}-message` : undefined;

  return (
    <div className={cn('space-y-2', className)}>
      <Label htmlFor={id}>
        {label}
        {required && <span className="ml-0.5 text-destructive">*</span>}
      </Label>
      <div aria-describedby={messageId}>{children(id)}</div>
      {error ? (
        <p id={messageId} role="alert" className="text-sm font-medium text-destructive">
          {error}
        </p>
      ) : hint ? (
        <p id={messageId} className="text-sm text-muted-foreground">
          {hint}
        </p>
      ) : null}
    </div>
  );
}
