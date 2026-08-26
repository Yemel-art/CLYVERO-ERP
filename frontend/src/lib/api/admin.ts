import { apiClient, unwrap } from './client';
import type { ApiResponse, ApiMeta } from '@/types/api';

export interface SchoolSettings {
  id: string;
  name: string;
  slug: string;
  school_code: string;
  address: string | null;
  phone: string | null;
  email: string | null;
  motto: string | null;
  logo_url: string | null;
  secondary_logo_url: string | null;
  document_header_image_url: string | null;
  document_header_image_settings: DocumentHeaderImageSettings;
  currency: string;
  default_locale: 'fr' | 'en';
  report_card_remarks: PerformanceRemark[] | null;
  honor_roll_rules: HonorRollRule[] | null;
  primary_color: string;
  secondary_color: string;
  document_header: string | null;
  document_footer: string | null;
  principal_name: string | null;
  principal_title: string | null;
  education_systems: Array<'secondary_general' | 'secondary_technical'>;
}

export interface DocumentHeaderImageSettings {
  mode: 'fit' | 'full_width';
  width: number;
  max_height: number;
  alignment: 'left' | 'center' | 'right';
}

export interface PerformanceRemark {
  minimum: number;
  fr: string;
  en: string;
}

export interface HonorRollRule extends PerformanceRemark {
  max_rank: number | null;
}

export interface AppSettings {
  school: SchoolSettings | null;
  app: { name: string; version: string; env: string };
}

export interface AdminUser {
  id: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  last_login_at: string | null;
  roles: Array<{ id: string; name: string; display_name: string }>;
}

export interface AuditLogRow {
  id: string;
  created_at: string;
  user: { id: string; full_name: string; email: string } | null;
  module: string;
  action: string;
  subject_type: string | null;
  subject_id: string | null;
  ip: string | null;
  user_agent: string | null;
  metadata: Record<string, unknown> | null;
}

interface Paginated<T> {
  data: T[];
  meta: Required<Pick<ApiMeta, 'page' | 'per_page' | 'total' | 'last_page'>>;
}

function pageify<T>(data: ApiResponse<T[]>): Paginated<T> {
  if (!data.success) throw new Error(data.message);
  return {
    data: data.data ?? [],
    meta: {
      page: Number(data.meta?.page ?? 1),
      per_page: Number(data.meta?.per_page ?? 25),
      total: Number(data.meta?.total ?? 0),
      last_page: Number(data.meta?.last_page ?? 1),
    },
  };
}

export const adminApi = {
  // Settings
  async getSettings(): Promise<AppSettings> {
    const { data } = await apiClient.get<ApiResponse<AppSettings>>('/settings');
    return unwrap(data);
  },
  async updateSchool(payload: Partial<SchoolSettings>) {
    const { data } = await apiClient.patch<ApiResponse<SchoolSettings>>('/settings', payload);
    return unwrap(data);
  },
  async uploadSchoolLogo(file: File, kind: 'primary' | 'secondary' | 'document_header' = 'primary') {
    const body = new FormData(); body.append('logo', file); body.append('kind', kind);
    const { data } = await apiClient.post<ApiResponse<{ logo_url: string | null; secondary_logo_url: string | null; document_header_image_url: string | null }>>('/settings/logo', body);
    return unwrap(data);
  },

  // Users
  async listUsers(params: { q?: string; role?: string; is_active?: boolean; page?: number; per_page?: number } = {}) {
    const { data } = await apiClient.get<ApiResponse<AdminUser[]>>('/users', { params });
    return pageify(data);
  },
  async createUser(payload: { first_name: string; last_name: string; email: string; phone?: string; password: string; role: string; is_active?: boolean }) {
    const { data } = await apiClient.post<ApiResponse<AdminUser>>('/users', payload);
    return unwrap(data);
  },
  async updateUser(id: string, payload: Partial<AdminUser> & { role?: string }) {
    const { data } = await apiClient.patch<ApiResponse<AdminUser>>(`/users/${id}`, payload);
    return unwrap(data);
  },
  async resetPassword(id: string, password: string) {
    const { data } = await apiClient.post<ApiResponse<null>>(`/users/${id}/reset-password`, { password });
    if (!data.success) throw new Error(data.message);
  },

  // Audit
  async listAuditLogs(params: { module?: string; action?: string; user_id?: string; from?: string; to?: string; page?: number; per_page?: number } = {}) {
    const { data } = await apiClient.get<ApiResponse<AuditLogRow[]>>('/audit-logs', { params });
    return pageify(data);
  },
};
