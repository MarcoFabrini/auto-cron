import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Heading } from '@/components/ui';
import { UserAvatar } from '@/components/features';
import { useAuthStore } from '@/stores/useAuthStore';
import { useActiveMembership } from '@/hooks/useActiveMembership';

/**
 * Topbar — header sticky mobile (md:hidden).
 * Titolo = nome organizzazione attiva (fallback brand app). Avatar a destra →
 * Impostazioni (la bottom nav non ha la tab Impostazioni).
 */
export function Topbar() {
  const { t } = useTranslation();
  const user = useAuthStore((s) => s.user);
  const membership = useActiveMembership();
  const title = membership?.organization.name?.trim() || t('app.name');

  return (
    <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-card px-4 py-3 pt-safe md:hidden">
      <Heading level={4} as="span" className="min-w-0 truncate">
        {title}
      </Heading>
      {user && (
        <Link
          to="/settings"
          aria-label={t('nav.settings')}
          className="shrink-0 rounded-full ring-offset-background transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          <UserAvatar user={user} size="sm" />
        </Link>
      )}
    </header>
  );
}
