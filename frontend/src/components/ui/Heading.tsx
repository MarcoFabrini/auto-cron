import { type HTMLAttributes, type ElementType } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * Heading atom — typography scale consistente per titoli pagina/sezione.
 *
 * `level` controlla sia il tag semantico (h1/h2/h3/...) sia lo stile.
 * Per separare tag e stile, override `as` o `className`.
 *
 * @example
 * <Heading level={1}>Dashboard</Heading>           // h1, text-3xl
 * <Heading level={2}>Veicoli</Heading>             // h2, text-2xl
 * <Heading level={3} as="h2">Stats</Heading>       // h2 markup, h3 style
 */
const headingVariants = cva('scroll-m-20 tracking-tight text-foreground', {
  variants: {
    level: {
      1: 'text-2xl font-bold md:text-3xl',
      2: 'text-xl font-semibold md:text-2xl',
      3: 'text-lg font-semibold md:text-xl',
      4: 'text-base font-semibold md:text-lg',
    },
  },
  defaultVariants: { level: 1 },
});

type HeadingLevel = 1 | 2 | 3 | 4;

export interface HeadingProps
  extends HTMLAttributes<HTMLHeadingElement>,
    VariantProps<typeof headingVariants> {
  /** Override del tag HTML (default: matchato a level) */
  as?: ElementType;
  level?: HeadingLevel;
}

export function Heading({ className, level = 1, as, ...props }: HeadingProps) {
  const Component = (as ?? (`h${level}` as ElementType));
  return <Component className={cn(headingVariants({ level }), className)} {...props} />;
}
