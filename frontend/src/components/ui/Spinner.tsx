import { Loader2 } from 'lucide-react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * Spinner atom — loading indicator inline.
 *
 * @example
 * <Spinner />                    // medium
 * <Spinner size="sm" />          // dentro button
 * <Spinner size="lg" />          // pagina full
 */
const spinnerVariants = cva('animate-spin text-muted-foreground', {
  variants: {
    size: {
      sm: 'size-4',
      md: 'size-6',
      lg: 'size-10',
    },
  },
  defaultVariants: { size: 'md' },
});

export interface SpinnerProps extends VariantProps<typeof spinnerVariants> {
  className?: string;
  /** Label per screen reader (default "Loading...") */
  label?: string;
}

export function Spinner({ size, className, label = 'Loading...' }: SpinnerProps) {
  return (
    <span role="status" className="inline-flex" aria-label={label}>
      <Loader2 className={cn(spinnerVariants({ size }), className)} />
      <span className="sr-only">{label}</span>
    </span>
  );
}
