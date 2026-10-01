import { Bell, Fuel, Receipt, Wrench, type LucideIcon } from 'lucide-react';

/**
 * Cosa si può aggiungere a un veicolo: rotta `/new` (con `?vehicleId=`), label i18n e icona.
 * Unica lista per il "+" mobile (QuickAddSheet) e il menu "Aggiungi" del dettaglio su desktop.
 */
export const VEHICLE_RECORD_ACTIONS: { path: string; labelKey: string; Icon: LucideIcon }[] = [
  { path: '/maintenance/new', labelKey: 'maintenance.new', Icon: Wrench },
  { path: '/refueling/new', labelKey: 'refueling.new', Icon: Fuel },
  { path: '/expenses/new', labelKey: 'expense.new', Icon: Receipt },
  { path: '/reminders/new', labelKey: 'reminder.new', Icon: Bell },
];
