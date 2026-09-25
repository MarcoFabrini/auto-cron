import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  deleteAttachment,
  downloadAttachment,
  listAttachments,
  uploadAttachment,
} from '@/api/endpoints/attachments';
import type { AttachmentEntityType } from '@/api/types/attachment';

export const attachmentKeys = {
  all: ['attachments'] as const,
  byEntity: (entityType: AttachmentEntityType, entityId: number) =>
    [...attachmentKeys.all, entityType, entityId] as const,
  blob: (id: number) => [...attachmentKeys.all, 'blob', id] as const,
};

export function useAttachments(entityType: AttachmentEntityType, entityId: number) {
  return useQuery({
    queryKey: attachmentKeys.byEntity(entityType, entityId),
    queryFn: () => listAttachments(entityType, entityId),
    enabled: entityId > 0,
  });
}

/** Blob cache per thumbnail/preview (immagini). staleTime infinito: i file sono immutabili. */
export function useAttachmentBlob(id: number, enabled = true) {
  return useQuery({
    queryKey: attachmentKeys.blob(id),
    queryFn: () => downloadAttachment(id),
    enabled: enabled && id > 0,
    staleTime: Number.POSITIVE_INFINITY,
    gcTime: 10 * 60 * 1000,
  });
}

export function useUploadAttachment(entityType: AttachmentEntityType, entityId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (file: File) => uploadAttachment({ entityType, entityId, file }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: attachmentKeys.byEntity(entityType, entityId) });
    },
  });
}

export function useDeleteAttachment(entityType: AttachmentEntityType, entityId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteAttachment(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: attachmentKeys.byEntity(entityType, entityId) });
    },
  });
}
