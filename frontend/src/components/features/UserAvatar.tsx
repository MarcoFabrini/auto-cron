import { cn } from '@/lib/utils';
import { useAvatarBlob } from '@/hooks/useAvatar';
import { useObjectUrl } from '@/hooks/useObjectUrl';
import type { User } from '@/stores/useAuthStore';

/**
 * UserAvatar — foto profilo dell'utente corrente (blob autenticato) o
 * fallback con le iniziali. La query del blob parte solo se hasAvatar.
 */
export interface UserAvatarProps {
  user: User;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export function UserAvatar({ user, size = 'md', className }: UserAvatarProps) {
  const blob = useAvatarBlob();
  const url = useObjectUrl(blob.data);

  const initials = `${user.firstName.charAt(0)}${user.lastName.charAt(0)}`.toUpperCase();
  const sizeClasses =
    size === 'lg'
      ? 'size-20 text-2xl'
      : size === 'sm'
        ? 'size-9 text-sm'
        : 'size-14 text-lg';

  if (user.hasAvatar && url) {
    return (
      <img
        src={url}
        alt={`${user.firstName} ${user.lastName}`}
        className={cn('shrink-0 rounded-full object-cover', sizeClasses, className)}
      />
    );
  }

  return (
    <div
      aria-hidden
      className={cn(
        'flex shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary',
        sizeClasses,
        className,
      )}
    >
      {initials}
    </div>
  );
}
