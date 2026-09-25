import { Link, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * NavItem molecule — voce nav riusabile in Sidebar e BottomNav.
 *
 * Stato active (confronto pathname con `to`) reso ben visibile:
 * - bottom nav (horizontal): pill colorata dietro l'icona + testo primary in grassetto;
 * - sidebar (vertical): riga piena primary in grassetto.
 *
 * @example
 * <NavItem to="/vehicles" labelKey="nav.vehicles" Icon={Car} orientation="vertical" />
 * <NavItem to="/" labelKey="nav.dashboard" Icon={Home} orientation="horizontal" />
 */
export interface NavItemProps {
  to: string;
  labelKey: string;
  Icon: LucideIcon;
  /** Layout: sidebar (icon+label inline) vs bottom nav (icon sopra label) */
  orientation: 'vertical' | 'horizontal';
}

export function NavItem({ to, labelKey, Icon, orientation }: NavItemProps) {
  const { t } = useTranslation();
  const { pathname } = useLocation();
  const active = pathname === to || (to !== '/' && pathname.startsWith(`${to}/`));

  const isHorizontal = orientation === 'horizontal';

  return (
    <Link
      to={to}
      aria-current={active ? 'page' : undefined}
      aria-label={isHorizontal ? t(labelKey) : undefined}
      title={isHorizontal ? t(labelKey) : undefined}
      className={cn(
        'min-h-touch transition-colors',
        isHorizontal
          ? 'flex flex-1 flex-col items-center justify-center gap-1 py-2 text-xs'
          : 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium',
        // sidebar (vertical)
        !isHorizontal &&
          (active
            ? 'bg-primary font-semibold text-primary-foreground shadow-sm'
            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'),
        // bottom nav (horizontal)
        isHorizontal &&
          (active ? 'font-semibold text-primary' : 'font-medium text-muted-foreground hover:text-accent-foreground'),
      )}
    >
      {isHorizontal ? (
        <span
          className={cn(
            'flex items-center justify-center rounded-full px-5 py-1 transition-colors',
            active && 'bg-primary/10',
          )}
        >
          <Icon className="size-6" />
        </span>
      ) : (
        <>
          <Icon className="size-5" />
          <span>{t(labelKey)}</span>
        </>
      )}
    </Link>
  );
}
