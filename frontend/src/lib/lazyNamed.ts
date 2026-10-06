import { lazy, type ComponentType, type LazyExoticComponent } from 'react';
import { clearChunkReloadFlag, isChunkLoadError, reloadOnceForChunkError } from '@/lib/chunkRecovery';

/** Mai risolta: tiene attivo il fallback di Suspense mentre la pagina si ricarica. */
const forever = new Promise<never>(() => undefined);

/**
 * `React.lazy` per un export con nome (le pagine e le sezioni dell'app non hanno default export):
 *
 *   const SettingsPage = lazyNamed(() => import('./pages/SettingsPage'), 'SettingsPage');
 *
 * In più gestisce il chunk sparito dopo un deploy (vedi `lib/chunkRecovery`): ricarica la pagina
 * una volta sola e lascia il fallback a vista; al secondo fallimento rilancia l'errore, che arriva
 * all'ErrorBoundary. Un import riuscito riarma il recupero.
 */
export function lazyNamed<K extends string, P extends object>(
  load: () => Promise<Record<K, ComponentType<P>>>,
  name: K,
): LazyExoticComponent<ComponentType<P>> {
  return lazy(async () => {
    try {
      const module: Record<K, ComponentType<P>> | undefined = await load();
      // Con `vite:preloadError` gestito (preventDefault) Vite risolve l'import con `undefined`.
      if (module === undefined) return forever;
      clearChunkReloadFlag();
      return { default: module[name] };
    } catch (error) {
      if (isChunkLoadError(error) && reloadOnceForChunkError()) return forever;
      throw error;
    }
  });
}
