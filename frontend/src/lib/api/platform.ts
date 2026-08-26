import { apiClient, unwrap } from './client';
import type { ApiResponse, ApiMeta } from '@/types/api';

export interface PlatformDocumentHeaderImageSettings {
  mode: 'fit' | 'full_width'; width: number; max_height: number; alignment: 'left' | 'center' | 'right';
}

export interface PlatformSchool {
  id: string; school_code: string; slug: string; school_name: string;
  slogan: string | null; logo_url: string | null; secondary_logo_url: string | null;
  document_header_image_url: string | null;
  document_header_image_settings: PlatformDocumentHeaderImageSettings;
  email: string; phone: string | null; address: string | null; website: string | null;
  city: string | null; country: string | null;
  default_locale: 'fr' | 'en'; education_systems: string[]; is_active: boolean;
  primary_color: string | null; secondary_color: string | null;
  document_header: string | null; document_footer: string | null;
  principal_name: string | null; principal_title: string | null;
  current_academic_year: { id: string; title: string } | null; created_at: string | null;
}
export interface CreateSchoolPayload {
  school_name: string; school_code?: string; email: string; phone?: string;
  address?: string; city?: string; country?: string; default_locale: 'fr' | 'en'; education_systems: string[];
  administrator_first_name: string; administrator_last_name: string;
  administrator_email: string; administrator_password: string;
}
export interface PlatformOverview {
  schools_total: number; schools_active: number; schools_inactive: number;
  school_administrators: number; students_total: number; teachers_total: number;
  recent_schools: PlatformSchool[];
}
export interface SchoolAdministratorCredentials {
  id: string; first_name: string; last_name: string; full_name: string;
  email: string; is_active: boolean; last_login_at: string | null;
}
export interface UpdateSchoolAdministratorCredentialsPayload {
  first_name: string; last_name: string; email: string;
  administrator_password?: string;
  administrator_password_confirmation?: string;
}
export type UpdatePlatformSchoolPayload = Pick<PlatformSchool,
  'school_name' | 'school_code' | 'slug' | 'slogan' | 'email' | 'phone' | 'address' |
  'website' | 'city' | 'country' | 'default_locale' | 'education_systems' |
  'primary_color' | 'secondary_color' | 'document_header' | 'document_footer' |
  'document_header_image_settings' | 'principal_name' | 'principal_title'
>;

export const platformApi = {
  async overview(signal?: AbortSignal): Promise<PlatformOverview> {
    const { data } = await apiClient.get<ApiResponse<PlatformOverview>>('/platform/overview', {
      signal,
      timeout: 15_000,
    });
    return unwrap(data);
  },
  async listSchools(): Promise<{ data: PlatformSchool[]; meta?: ApiMeta }> {
    const { data } = await apiClient.get<ApiResponse<PlatformSchool[]>>('/platform/schools');
    if (!data.success) throw new Error(data.message);
    return { data: data.data ?? [], meta: data.meta };
  },
  async createSchool(payload: CreateSchoolPayload): Promise<PlatformSchool> {
    const { data } = await apiClient.post<ApiResponse<PlatformSchool>>('/platform/schools', payload);
    return unwrap(data);
  },
  async updateSchool(id: string, payload: UpdatePlatformSchoolPayload): Promise<PlatformSchool> {
    const { data } = await apiClient.patch<ApiResponse<PlatformSchool>>(`/platform/schools/${id}`, payload);
    return unwrap(data);
  },
  async uploadSchoolLogo(id: string, file: File, kind: 'primary' | 'secondary' | 'document_header'): Promise<PlatformSchool> {
    const body = new FormData();
    body.append('logo', file);
    body.append('kind', kind);
    const { data } = await apiClient.post<ApiResponse<PlatformSchool>>(`/platform/schools/${id}/logo`, body);
    return unwrap(data);
  },
  async getSchoolAdministratorCredentials(id: string): Promise<SchoolAdministratorCredentials> {
    const { data } = await apiClient.get<ApiResponse<SchoolAdministratorCredentials>>(
      `/platform/schools/${id}/administrator-credentials`,
    );
    return unwrap(data);
  },
  async updateSchoolAdministratorCredentials(
    id: string,
    payload: UpdateSchoolAdministratorCredentialsPayload,
  ): Promise<SchoolAdministratorCredentials> {
    const { data } = await apiClient.patch<ApiResponse<SchoolAdministratorCredentials>>(
      `/platform/schools/${id}/administrator-credentials`,
      payload,
    );
    return unwrap(data);
  },
  async setSchoolActive(id: string, isActive: boolean): Promise<PlatformSchool> {
    const { data } = await apiClient.patch<ApiResponse<PlatformSchool>>(`/platform/schools/${id}/status`, {
      is_active: isActive,
    });
    return unwrap(data);
  },
};
