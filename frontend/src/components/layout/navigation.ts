import {
  Bell,
  Car,
  Fuel,
  Home,
  Receipt,
  Settings,
  Wrench,
  type LucideIcon,
} from 'lucide-react';

/**
 * Navigation tree — single source of truth per Sidebar e BottomNav.
 * La Sidebar desktop mostra tutte le voci; la BottomNav mobile mostra solo le
 * voci `mobile` (Home + Promemoria), col pulsante "+" centrale iniettato in mezzo.
 */
export interface NavEntry {
  to: string;
  labelKey: string;
  Icon: LucideIcon;
  /** Mostrato nella bottom nav mobile? (solo Home e Promemoria) */
  mobile?: boolean;
}

export const NAVIGATION: NavEntry[] = [
  { to: '/', labelKey: 'nav.dashboard', Icon: Home, mobile: true },
  { to: '/vehicles', labelKey: 'nav.vehicles', Icon: Car, mobile: false },
  { to: '/maintenance', labelKey: 'nav.maintenance', Icon: Wrench, mobile: false },
  { to: '/refueling', labelKey: 'nav.refueling', Icon: Fuel, mobile: false },
  { to: '/expenses', labelKey: 'nav.expenses', Icon: Receipt, mobile: false },
  { to: '/reminders', labelKey: 'nav.reminders', Icon: Bell, mobile: true },
  { to: '/settings', labelKey: 'nav.settings', Icon: Settings, mobile: false },
];
