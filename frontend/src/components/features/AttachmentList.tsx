import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Download, FileText, Paperclip, Trash2 } from 'lucide-react';
import { Alert, Skeleton } from '@/components/ui';
import { EmptyState } from './EmptyState';
import { ConfirmDialog } from './ConfirmDialog';
import { useToast } from '@/hooks/useToast';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import { useObjectUrl } from '@/hooks/useObjectUrl';
import {
  attachmentKeys,
  useAttachmentBlob,
  useAttachments,
  useDeleteAttachment,
} from '@/hooks/useAttachments';
import { useQueryClient } from '@tanstack/react-query';
import { downloadAttachment } from '@/api/endpoints/attachments';
import { formatBytes } from '@/lib/format';
import type { Attachment, AttachmentEntityType } from '@/api/types/attachment';

export interface AttachmentListProps {
  entityType: AttachmentEntityType;
  entityId: number;
}

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export function AttachmentList({ entityType, entityId }: AttachmentListProps) {
  const { t } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const { data, isLoading, error } = useAttachments(entityType, entityId);

  if (isLoading) {
    return (
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
        <Skeleton className="aspect-square w-full rounded-lg" />
        <Skeleton className="aspect-square w-full rounded-lg" />
      </div>
    );
  }

  if (error) return <Alert variant="error">{errorMessage(error)}</Alert>;

  if (!data || data.length === 0) {
    return (
      <EmptyState
        Icon={Paperclip}
        title={t('attachment.empty.title')}
        description={t('attachment.empty.description')}
      />
    );
  }

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
      {data.map((a) => (
        <AttachmentItem key={a.id} attachment={a} entityType={entityType} entityId={entityId} />
      ))}
    </div>
  );
}

interface AttachmentItemProps {
  attachment: Attachment;
  entityType: AttachmentEntityType;
  entityId: number;
}

function AttachmentItem({ attachment, entityType, entityId }: AttachmentItemProps) {
  const a = attachment;
  const { t } = useTranslation();
  const { toast } = useToast();
  const errorMessage = useApiErrorMessage();
  const qc = useQueryClient();
  const del = useDeleteAttachment(entityType, entityId);
  const [confirmOpen, setConfirmOpen] = useState(false);

  const thumb = useAttachmentBlob(a.id, a.isImage);
  const thumbUrl = useObjectUrl(thumb.data);

  const onDownload = async () => {
    try {
      const blob = await qc.ensureQueryData({
        queryKey: attachmentKeys.blob(a.id),
        queryFn: () => downloadAttachment(a.id),
        staleTime: Number.POSITIVE_INFINITY,
      });
      triggerDownload(blob, a.originalFilename);
    } catch (err) {
      toast({ title: errorMessage(err), variant: 'error' });
    }
  };

  return (
    <div className="group relative overflow-hidden rounded-lg border bg-card">
      <button
        type="button"
        onClick={onDownload}
        className="block aspect-square w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label={t('attachment.open', { name: a.originalFilename })}
      >
        {a.isImage && thumbUrl ? (
          <img src={thumbUrl} alt={a.originalFilename} className="size-full object-cover" />
        ) : (
          <div className="flex size-full items-center justify-center bg-muted">
            {a.isImage ? (
              <Skeleton className="size-full" />
            ) : (
              <FileText className="size-10 text-muted-foreground" />
            )}
          </div>
        )}
        <span className="pointer-events-none absolute right-2 top-2 rounded-md bg-background/80 p-1 opacity-0 transition-opacity group-hover:opacity-100">
          <Download className="size-4" />
        </span>
      </button>

      <div className="flex items-center gap-2 border-t px-2 py-1.5">
        <div className="min-w-0 flex-1">
          <p className="truncate text-xs font-medium" title={a.originalFilename}>
            {a.originalFilename}
          </p>
          <p className="text-[11px] text-muted-foreground">{formatBytes(a.sizeBytes)}</p>
        </div>
        <button
          type="button"
          onClick={() => setConfirmOpen(true)}
          className="shrink-0 rounded-md p-1 text-muted-foreground hover:text-destructive focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label={t('actions.delete')}
        >
          <Trash2 className="size-4" />
        </button>
      </div>

      <ConfirmDialog
        open={confirmOpen}
        onOpenChange={setConfirmOpen}
        title={t('attachment.delete.title')}
        description={t('attachment.delete.description')}
        isPending={del.isPending}
        confirmLabel={t('actions.delete')}
        onConfirm={() =>
          del.mutate(a.id, {
            onSuccess: () => {
              setConfirmOpen(false);
              toast({ title: t('attachment.deleted'), variant: 'success' });
            },
            onError: (err) => toast({ title: errorMessage(err), variant: 'error' }),
          })
        }
      />
    </div>
  );
}
