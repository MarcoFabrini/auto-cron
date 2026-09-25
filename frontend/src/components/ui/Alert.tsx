import { forwardRef, type HTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { AlertCircle, CheckCircle2, Info, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Alert atom — banner inline per messaggi (info, error, success, warning).
 *
 * @example
 * <Alert variant="error">Email o password non validi</Alert>
 * <Alert variant="success" title="Salvato">Modifiche salvate correttamente.</Alert>
 */
const alertVariants = cva(
  'relative flex w-full gap-3 rounded-md border px-4 py-3 text-sm [&>svg]:size-4 [&>svg]:shrink-0 [&>svg]:translate-y-0.5',
  {
    variants: {
      variant: {
        info: 'border-border bg-card text-card-foreground [&>svg]:text-primary',
        error:
          'border-destructive/50 bg-destructive/10 text-destructive [&>svg]:text-destructive dark:border-destructive',
        success:
          'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-400 [&>svg]:text-green-600',
        warning:
          'border-yellow-500/50 bg-yellow-500/10 text-yellow-800 dark:text-yellow-300 [&>svg]:text-yellow-600',
      },
    },
    defaultVariants: { variant: 'info' },
  },
);

const iconByVariant = {
  info: Info,
  error: AlertCircle,
  success: CheckCircle2,
  warning: AlertTriangle,
} as const;

export interface AlertProps
  extends HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof alertVariants> {
  /** Titolo bold sopra il body */
  title?: string;
  /** Nasconde icona automatica */
  hideIcon?: boolean;
}

export const Alert = forwardRef<HTMLDivElement, AlertProps>(
  ({ className, variant = 'info', title, hideIcon, children, ...props }, ref) => {
    const Icon = iconByVariant[variant ?? 'info'];
    return (
      <div ref={ref} role="alert" className={cn(alertVariants({ variant }), className)} {...props}>
        {!hideIcon && <Icon />}
        <div className="flex-1 space-y-1">
          {title && <div className="font-semibold leading-none">{title}</div>}
          {children && <div className="leading-relaxed">{children}</div>}
        </div>
      </div>
    );
  },
);
Alert.displayName = 'Alert';
