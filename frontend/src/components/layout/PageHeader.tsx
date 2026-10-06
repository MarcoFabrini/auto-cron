import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { Button, Heading, Text } from '@/components/ui';

/**
 * PageHeader — riga in cima alla pagina: back button opzionale + titolo/descrizione
 * opzionali a sinistra, slot azione (es. "+ Nuovo") a destra.
 *
 * I titoli che duplicano una voce di menu sono omessi (la nav indica già dove siamo):
 * le pagine lista passano solo l'azione, che resta allineata a destra anche su mobile.
 * Ogni pagina ha esattamente un h1: quando il titolo visibile è omesso, `srTitle` rende un h1
 * `sr-only` con l'etichetta della pagina (la voce di menu), così gli screen reader hanno il loro
 * punto di riferimento. Se non c'è nulla da mostrare né da annunciare, non rende nulla.
 *
 * @example
 * <PageHeader srTitle={t('nav.vehicles')} action={<Button>+ Nuovo</Button>} />
 * <PageHeader title="Modifica" onBack={() => navigate('/vehicles')} />
 */
export interface PageHeaderProps {
  title?: string;
  /** h1 solo per screen reader, usato quando `title` non c'è (le liste non ripetono la voce di menu). */
  srTitle?: string;
  description?: string;
  /** Slot per action button(s) a destra. */
  action?: ReactNode;
  /** Se presente, mostra una freccia "indietro" a sinistra del titolo. */
  onBack?: () => void;
}

export function PageHeader({ title, srTitle, description, action, onBack }: PageHeaderProps) {
  const { t } = useTranslation();
  const srOnlyTitle = !title && srTitle ? <Heading level={1} className="sr-only">{srTitle}</Heading> : null;

  if (!title && !description && !action && !onBack) return srOnlyTitle;

  return (
    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      {srOnlyTitle}
      <div className="flex items-start gap-2">
        {onBack && (
          <Button
            variant="ghost"
            size="icon"
            onClick={onBack}
            aria-label={t('actions.back')}
            className="-ml-2 shrink-0"
          >
            <ArrowLeft />
          </Button>
        )}
        {(title || description) && (
          <div className="min-w-0 space-y-1">
            {title && <Heading level={1}>{title}</Heading>}
            {description && <Text variant="muted">{description}</Text>}
          </div>
        )}
      </div>
      {action && (
        <div className="flex shrink-0 items-center gap-2 self-end md:self-auto">{action}</div>
      )}
    </div>
  );
}
