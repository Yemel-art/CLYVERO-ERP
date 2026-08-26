import { apiClient, unwrap } from './client';
import type { ApiResponse } from '@/types/api';

export type NotificationType = 'info' | 'success' | 'warning' | 'danger';

export interface Notification {
  id: string;
  type: NotificationType;
  title: string;
  body: string | null;
  data: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string;
}

export interface NotificationBox {
  data: Notification[];
  unread_count: number;
}

export const notificationsApi = {
  async list(params: { unread_only?: boolean; limit?: number } = {}): Promise<NotificationBox> {
    const { data } = await apiClient.get<ApiResponse<NotificationBox>>('/notifications', { params });
    return unwrap(data);
  },
  async markRead(id: string) {
    const { data } = await apiClient.post<ApiResponse<null>>(`/notifications/${id}/read`);
    if (!data.success) throw new Error(data.message);
  },
  async markAllRead() {
    const { data } = await apiClient.post<ApiResponse<{ marked: number }>>('/notifications/mark-all-read');
    return unwrap(data);
  },
  async broadcast(payload: { title: string; body?: string; type?: NotificationType; role?: string }) {
    const { data } = await apiClient.post<ApiResponse<{ recipients: number }>>('/notifications/broadcast', payload);
    return unwrap(data);
  },
};
