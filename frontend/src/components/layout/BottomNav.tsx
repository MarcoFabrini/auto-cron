import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus } from 'lucide-react';
import { NavItem } from './NavItem';
import { NAVIGATION } from './navigation';
import { QuickAddSheet } from '@/components/features';

/**
 * BottomNav — bottom tab bar fissa mobile. Nascosta su desktop (md+).
 * 3 slot: Home (sx) · pulsante "+" centrale rialzato (hub aggiunte) · Promemoria (dx).
 * `pb-safe` accomoda l'home indicator iPhone in PWA standalone.
 */
export function BottomNav() {
  const { t } = useTranslation();
  const [addOpen, setAddOpen] = useState(false);
  const mobileItems = NAVIGATION.filter((entry) => entry.mobile);
  const home = mobileItems[0];
  const reminders = mobileItems[1];

  return (
    <>
      <nav
        aria-label="Primary mobile"
        className="fixed bottom-0 left-0 right-0 z-20 flex items-center border-t bg-card pb-safe md:hidden"
      >
        {home && <NavItem {...home} orientation="horizontal" />}

        <div className="flex flex-1 justify-center">
          <button
            type="button"
            onClick={() => setAddOpen(true)}
            aria-label={t('quick_add.open')}
            className="-mt-6 flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          >
            <Plus className="size-7" />
          </button>
        </div>

        {reminders && <NavItem {...reminders} orientation="horizontal" />}
      </nav>

      <QuickAddSheet open={addOpen} onOpenChange={setAddOpen} />
    </>
  );
}
