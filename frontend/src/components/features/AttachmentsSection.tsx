import { AttachmentUploader } from './AttachmentUploader';
import { AttachmentList } from './AttachmentList';
import type { AttachmentEntityType } from '@/api/types/attachment';

export interface AttachmentsSectionProps {
  entityType: AttachmentEntityType;
  entityId: number;
}

/**
 * Sezione allegati riusabile: bottone upload allineato a destra + griglia
 * thumbnail. Punto unico d'integrazione per pagine veicolo/manutenzione/
 * rifornimento.
 */
export function AttachmentsSection({ entityType, entityId }: AttachmentsSectionProps) {
  return (
    <div className="space-y-3 pt-2">
      <div className="flex justify-end">
        <AttachmentUploader entityType={entityType} entityId={entityId} />
      </div>
      <AttachmentList entityType={entityType} entityId={entityId} />
    </div>
  );
}
