import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Skeleton atom — loading placeholder con shimmer.
 *
 * @example
 * <Skeleton className="h-4 w-32" />
 * <Skeleton className="size-12 rounded-full" />
 */
export function Skeleton({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('animate-pulse rounded-md bg-muted', className)} {...props} />;
}
