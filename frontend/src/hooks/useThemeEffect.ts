import { useEffect } from 'react';
import { applyTheme, useThemeStore } from '@/stores/useThemeStore';

/**
 * useThemeEffect — applica la class 'dark' su <html> in base al theme store.
 * Riascolta i cambi di prefers-color-scheme quando theme='system'.
 */
export function useThemeEffect() {
  const theme = useThemeStore((s) => s.theme);

  useEffect(() => {
    applyTheme(theme);

    if (theme !== 'system') return;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = () => applyTheme('system');
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, [theme]);
}
