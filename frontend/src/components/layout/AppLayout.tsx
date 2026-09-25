import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Topbar } from './Topbar';
import { BottomNav } from './BottomNav';
import { EmailVerificationBanner } from './EmailVerificationBanner';

/**
 * AppLayout — layout authenticated:
 * - mobile: Topbar in alto + content scrollabile + BottomNav fissa in basso
 * - desktop (md+): Sidebar a sinistra + content
 *
 * Padding bottom contenuto = 24 (96px) per non finire sotto BottomNav.
 */
export function AppLayout() {
  return (
    <div className="flex h-dvh flex-col overflow-hidden md:flex-row">
      <Sidebar />
      <Topbar />
      <main className="flex-1 overflow-y-auto overscroll-contain px-4 pb-24 pt-4 md:px-8 md:pb-8">
        <EmailVerificationBanner />
        <Outlet />
      </main>
      <BottomNav />
    </div>
  );
}
