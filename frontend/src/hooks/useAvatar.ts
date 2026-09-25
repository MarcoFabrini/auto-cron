import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { deleteAvatar, fetchAvatar, uploadAvatar } from '@/api/endpoints/avatar';
import { useAuthStore } from '@/stores/useAuthStore';

export const avatarKeys = {
  /** Chiave per-utente: cambia utente → cambia cache. */
  blob: (userId: number) => ['avatar', userId] as const,
};

/** Blob della foto profilo dell'utente corrente (cache fino a upload/delete). */
export function useAvatarBlob() {
  const user = useAuthStore((s) => s.user);
  return useQuery({
    queryKey: avatarKeys.blob(user?.id ?? 0),
    queryFn: fetchAvatar,
    enabled: !!user && user.hasAvatar,
    staleTime: Number.POSITIVE_INFINITY,
    gcTime: 10 * 60 * 1000,
  });
}

/** Aggiorna lo store senza round-trip /me: il backend è la fonte di hasAvatar. */
function setHasAvatar(hasAvatar: boolean) {
  const { user, setUser } = useAuthStore.getState();
  if (user) setUser({ ...user, hasAvatar });
}

export function useUploadAvatar() {
  const qc = useQueryClient();
  const userId = useAuthStore((s) => s.user?.id ?? 0);
  return useMutation({
    mutationFn: (file: File) => uploadAvatar(file),
    onSuccess: () => {
      setHasAvatar(true);
      void qc.invalidateQueries({ queryKey: avatarKeys.blob(userId) });
    },
  });
}

export function useDeleteAvatar() {
  const qc = useQueryClient();
  const userId = useAuthStore((s) => s.user?.id ?? 0);
  return useMutation({
    mutationFn: deleteAvatar,
    onSuccess: () => {
      setHasAvatar(false);
      qc.removeQueries({ queryKey: avatarKeys.blob(userId) });
    },
  });
}
