import { type HTMLAttributes, type ElementType } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * Text atom — body copy con varianti semantiche.
 *
 * Variants:
 * - default: testo body normale
 * - muted: testo secondario grigio
 * - small: dimensione piccola
 * - lead: testo introduttivo più grande
 *
 * @example
 * <Text>Body normale</Text>
 * <Text variant="muted">Hint o sottotitolo</Text>
 * <Text variant="small">Caption</Text>
 */
const textVariants = cva('', {
  variants: {
    variant: {
      default: 'text-base text-foreground leading-relaxed',
      muted: 'text-sm text-muted-foreground',
      small: 'text-xs text-muted-foreground',
      lead: 'text-lg text-muted-foreground md:text-xl',
    },
  },
  defaultVariants: { variant: 'default' },
});

export interface TextProps
  extends HTMLAttributes<HTMLParagraphElement>,
    VariantProps<typeof textVariants> {
  as?: ElementType;
}

export function Text({ className, variant, as: Component = 'p', ...props }: TextProps) {
  return <Component className={cn(textVariants({ variant }), className)} {...props} />;
}
