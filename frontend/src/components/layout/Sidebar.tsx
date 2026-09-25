import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { NavItem } from './NavItem';
import { NAVIGATION } from './navigation';
import { Heading } from '@/components/ui';
import { UserAvatar } from '@/components/features';
import { useActiveMembership } from '@/hooks/useActiveMembership';
import { useAuthStore } from '@/stores/useAuthStore';
import { cn } from '@/lib/utils';

/**
 * Sidebar — nav verticale desktop (md+). Nascosta su mobile.
 * Impostazioni separata in fondo, sotto le altre voci, con l'avatar utente
 * al posto dell'icona (coerente con la Topbar mobile).
 */
export function Sidebar() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const membership = useActiveMembership();
  const user = useAuthStore((s) => s.user);
  const title = membership?.organization.name?.trim() || t('app.name');

  const mainEntries = NAVIGATION.filter((entry) => entry.to !== '/settings');
  const settingsActive = pathname === '/settings' || pathname.startsWith('/settings/');

  return (
    <aside className="hidden md:flex md:w-56 md:shrink-0 md:flex-col md:overflow-y-auto md:overscroll-contain md:border-r md:bg-card md:p-4">
      <Heading level={4} className="mb-6 truncate">
        {title}
      </Heading>
      <nav aria-label="Primary" className="flex flex-1 flex-col gap-1">
        {mainEntries.map((entry) => (
          <NavItem key={entry.to} {...entry} orientation="vertical" />
        ))}
      </nav>
      {user && (
        <Link
          to="/settings"
          aria-current={settingsActive ? 'page' : undefined}
          className={cn(
            'min-h-touch mt-2 flex items-center gap-3 rounded-md border-t px-3 pt-3 text-sm font-medium',
            settingsActive
              ? 'font-semibold text-primary'
              : 'text-muted-foreground hover:text-accent-foreground',
          )}
        >
          <UserAvatar user={user} size="sm" />
          <span>{t('nav.settings')}</span>
        </Link>
      )}
    </aside>
  );
}
