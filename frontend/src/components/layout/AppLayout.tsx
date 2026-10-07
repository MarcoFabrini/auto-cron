import { Suspense, useRef } from 'react';
import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Topbar } from './Topbar';
import { BottomNav } from './BottomNav';
import { EmailVerificationBanner } from './EmailVerificationBanner';
import { PullToRefresh } from './PullToRefresh';
import { RouteFallback } from './RouteFallback';

/**
 * AppLayout — layout authenticated:
 * - mobile: Topbar in alto + content scrollabile + BottomNav fissa in basso
 * - desktop (md+): Sidebar a sinistra + content
 *
 * Le pagine sono in lazy: il fallback occupa solo l'area contenuto, la shell resta a vista.
 * Radice fixed alta `--app-height` (lib/appHeight): in PWA iOS il viewport è più corto dello schermo e la BottomNav (absolute, dentro la radice) deve comunque arrivare al bordo.
 * Padding bottom contenuto = `pb-nav-clearance` (altezza della BottomNav, inset iOS compreso).
 * `<main>` è il contenitore che scrolla (non window): è anche quello del pull-to-refresh della PWA installata.
 */
export function AppLayout() {
  const scrollRef = useRef<HTMLElement>(null);
  return (
    <div className="fixed inset-x-0 top-0 flex h-[var(--app-height,100%)] flex-col overflow-hidden md:flex-row">
      <Sidebar />
      <Topbar />
      <main
        ref={scrollRef}
        className="relative flex-1 overflow-y-auto overscroll-contain px-4 pb-nav-clearance pt-4 md:px-8 md:pb-8"
      >
        <PullToRefresh scrollRef={scrollRef} />
        <EmailVerificationBanner />
        <Suspense fallback={<RouteFallback />}>
          <Outlet />
        </Suspense>
      </main>
      <BottomNav />
    </div>
  );
}
