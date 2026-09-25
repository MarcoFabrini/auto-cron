import type { HTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * Badge atom — pill colorato per status / categoria / count.
 *
 * @example
 * <Badge>Default</Badge>
 * <Badge variant="success">Pagata</Badge>
 * <Badge variant="warning">In scadenza</Badge>
 * <Badge variant="destructive">Scaduta</Badge>
 * <Badge variant="outline">Bozza</Badge>
 */
const badgeVariants = cva(
  'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
  {
    variants: {
      variant: {
        default: 'border-transparent bg-primary text-primary-foreground',
        secondary: 'border-transparent bg-secondary text-secondary-foreground',
        success: 'border-transparent bg-green-500/15 text-green-700 dark:text-green-400',
        warning: 'border-transparent bg-yellow-500/15 text-yellow-800 dark:text-yellow-300',
        destructive: 'border-transparent bg-destructive/15 text-destructive',
        outline: 'border-border text-foreground',
      },
    },
    defaultVariants: { variant: 'default' },
  },
);

export interface BadgeProps
  extends HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
  return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}
