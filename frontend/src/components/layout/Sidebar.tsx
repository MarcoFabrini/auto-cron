import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { NavItem } from './NavItem';
import { NAVIGATION } from './navigation';
import { OrganizationSwitcher, UserAvatar } from '@/components/features';
import { useAuthStore } from '@/stores/useAuthStore';
import { cn } from '@/lib/utils';

/**
 * Sidebar — nav verticale desktop (md+). Nascosta su mobile. Solo le voci `desktop`: i record
 * dei veicoli (manutenzioni, rifornimenti, spese, promemoria) si raggiungono dal veicolo.
 * Impostazioni separata in fondo, sotto le altre voci, con l'avatar utente
 * al posto dell'icona (coerente con la Topbar mobile).
 */
export function Sidebar() {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const user = useAuthStore((s) => s.user);

  const mainEntries = NAVIGATION.filter((entry) => entry.desktop !== false && entry.to !== '/settings');
  const settingsActive = pathname === '/settings' || pathname.startsWith('/settings/');

  return (
    <aside className="hidden md:flex md:w-56 md:shrink-0 md:flex-col md:overflow-y-auto md:overscroll-contain md:border-r md:bg-card md:p-4">
      {/* Nome org (testo, non heading: precederebbe l'h1 della pagina); menu di cambio se ne ha più d'una. */}
      <OrganizationSwitcher as="p" className="mb-6 w-full" />
      <nav aria-label={t('nav.primary')} className="flex flex-1 flex-col gap-1">
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
