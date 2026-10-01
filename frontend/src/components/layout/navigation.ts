import { Bell, Car, Home, Settings, type LucideIcon } from 'lucide-react';

/**
 * Navigation tree — single source of truth per Sidebar e BottomNav.
 * La Sidebar desktop mostra le voci `desktop` (Dashboard, Veicoli, Impostazioni): manutenzioni,
 * rifornimenti, spese e promemoria si gestiscono dentro il veicolo, con le sue tab e il suo
 * "Aggiungi". La BottomNav mobile mostra solo le voci `mobile` (Home + Promemoria), col pulsante
 * "+" centrale iniettato in mezzo.
 */
export interface NavEntry {
  to: string;
  labelKey: string;
  Icon: LucideIcon;
  /** Mostrato nella bottom nav mobile? (solo Home e Promemoria) */
  mobile?: boolean;
  /** Mostrato nella sidebar desktop? (default sì) */
  desktop?: boolean;
}

export const NAVIGATION: NavEntry[] = [
  { to: '/', labelKey: 'nav.dashboard', Icon: Home, mobile: true },
  { to: '/vehicles', labelKey: 'nav.vehicles', Icon: Car, mobile: false },
  { to: '/reminders', labelKey: 'nav.reminders', Icon: Bell, mobile: true, desktop: false },
  { to: '/settings', labelKey: 'nav.settings', Icon: Settings, mobile: false },
];
