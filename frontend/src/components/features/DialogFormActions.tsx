import { useTranslation } from 'react-i18next';
import { Button, DialogFooter, Spinner } from '@/components/ui';

/**
 * DialogFormActions — footer standard dei dialog con form:
 * Annulla (chiude) + submit con spinner durante la mutation.
 */
export interface DialogFormActionsProps {
  isPending: boolean;
  onCancel: () => void;
  /** Label del bottone submit; default `actions.save`. */
  submitLabel?: string;
}

export function DialogFormActions({ isPending, onCancel, submitLabel }: DialogFormActionsProps) {
  const { t } = useTranslation();

  return (
    <DialogFooter>
      <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
        {t('actions.cancel')}
      </Button>
      <Button type="submit" disabled={isPending}>
        {isPending ? <Spinner size="sm" /> : (submitLabel ?? t('actions.save'))}
      </Button>
    </DialogFooter>
  );
}
