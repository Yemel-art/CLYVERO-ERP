'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationsApi, type NotificationType } from '@/lib/api/notifications';

export function useNotifications(
  params: { unread_only?: boolean; limit?: number } = {},
  enabled = true,
) {
  return useQuery({
    queryKey: ['notifications', params],
    queryFn: () => notificationsApi.list(params),
    enabled,
    refetchInterval: 60_000,
    refetchIntervalInBackground: false,
  });
}

export function useMarkNotificationRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => notificationsApi.markRead(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['notifications'] }); },
  });
}

export function useMarkAllNotificationsRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['notifications'] }); },
  });
}

export function useBroadcastNotification() {
  return useMutation({
    mutationFn: (payload: { title: string; body?: string; type?: NotificationType; role?: string }) =>
      notificationsApi.broadcast(payload),
  });
}
