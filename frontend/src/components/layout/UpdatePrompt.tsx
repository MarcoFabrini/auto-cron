import { useUpdatePrompt } from '@/hooks/useUpdatePrompt';

/** Montato una volta in main.tsx accanto al Toaster: nessun markup proprio, solo l'avviso di nuova versione. */
export function UpdatePrompt() {
  useUpdatePrompt();
  return null;
}
