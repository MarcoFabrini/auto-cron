import { useEffect, useState } from 'react';

/**
 * ObjectURL gestito: crea da blob, revoca al cleanup.
 * L'URL è associato al blob che l'ha generato: cambiando blob non si restituisce mai quello vecchio
 * (già revocato) per il render che precede l'effect, che mostrerebbe un'immagine rotta per un attimo.
 */
export function useObjectUrl(blob: Blob | undefined): string | undefined {
  const [current, setCurrent] = useState<{ blob: Blob; url: string }>();
  useEffect(() => {
    if (!blob) {
      setCurrent(undefined);
      return;
    }
    const url = URL.createObjectURL(blob);
    setCurrent({ blob, url });
    return () => URL.revokeObjectURL(url);
  }, [blob]);
  return current && current.blob === blob ? current.url : undefined;
}
