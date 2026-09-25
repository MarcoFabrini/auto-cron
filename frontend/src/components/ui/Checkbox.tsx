import { forwardRef, type InputHTMLAttributes } from 'react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Checkbox atom — native checkbox styled coerentemente con shadcn aesthetic.
 *
 * Compone con Label/FormField come gli altri input.
 *
 * @example
 * <label className="flex items-center gap-2">
 *   <Checkbox checked={x} onChange={(e) => setX(e.target.checked)} />
 *   <span>Pieno</span>
 * </label>
 */
export type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>;

export const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, ...props }, ref) => (
    <span className="relative inline-flex">
      <input
        ref={ref}
        type="checkbox"
        className={cn(
          'peer size-5 shrink-0 cursor-pointer appearance-none rounded border border-input bg-background',
          'checked:border-primary checked:bg-primary',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
          'disabled:cursor-not-allowed disabled:opacity-50',
          className,
        )}
        {...props}
      />
      <Check className="pointer-events-none absolute left-0.5 top-0.5 size-4 text-primary-foreground opacity-0 peer-checked:opacity-100" />
    </span>
  ),
);
Checkbox.displayName = 'Checkbox';
