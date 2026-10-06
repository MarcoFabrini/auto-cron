import { useEffect, useSyncExternalStore } from 'react';
import { useTranslation } from 'react-i18next';
import { ToastAction } from '@/components/ui';
import { toast } from '@/hooks/useToast';
import { getUpdateAvailable, refreshApp, subscribeUpdateAvailable } from '@/lib/pwaUpdate';

/** Il toast resta finché l'utente non lo chiude o preme "Aggiorna": un valore enorme al posto di Infinity (Radix lo accetta come numero). */
const PERSISTENT_MS = 24 * 60 * 60 * 1000;

/**
 * Mostra "Nuova versione disponibile — Aggiorna" quando un nuovo service worker ha preso il controllo
 * (lib/pwaUpdate). L'azione ricarica; non si ricarica mai da soli, per non perdere un form a metà.
 */
export function useUpdatePrompt(): void {
  const { t } = useTranslation();
  const available = useSyncExternalStore(subscribeUpdateAvailable, getUpdateAvailable, () => false);

  useEffect(() => {
    if (!available) return;
    const { dismiss } = toast({
      title: t('update_prompt.title'),
      description: t('update_prompt.description'),
      duration: PERSISTENT_MS,
      action: (
        <ToastAction
          altText={t('update_prompt.action_alt')}
          onClick={() => {
            void refreshApp();
          }}
        >
          {t('update_prompt.action')}
        </ToastAction>
      ),
    });
    return dismiss;
  }, [available, t]);
}
