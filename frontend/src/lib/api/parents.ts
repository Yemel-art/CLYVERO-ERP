import { apiClient, unwrap } from './client';
import type { ApiResponse, ApiMeta } from '@/types/api';
import type { ParentGuardian, ParentFilters, ParentStatistics } from '@/types/parent';

interface PaginatedParents {
  data: ParentGuardian[];
  meta: Required<Pick<ApiMeta, 'page' | 'per_page' | 'total' | 'last_page'>>;
}

function toQuery(f: ParentFilters) {
  const p: Record<string, string | number | undefined> = {
    page: f.page, per_page: f.per_page,
  };
  if (f.q) p.q = f.q;
  if (f.gender) p.gender = f.gender;
  if (f.is_active !== undefined) p.is_active = f.is_active ? 1 : 0;
  if (f.has_children) p.has_children = 1;
  if (f.include_archived) p.include_archived = 1;
  return p;
}

export const parentsApi = {
  async list(filters: ParentFilters = {}): Promise<PaginatedParents> {
    const { data } = await apiClient.get<ApiResponse<ParentGuardian[]>>('/parents', { params: toQuery(filters) });
    if (!data.success) throw new Error(data.message);
    return {
      data: data.data ?? [],
      meta: {
        page: Number(data.meta?.page ?? 1),
        per_page: Number(data.meta?.per_page ?? 20),
        total: Number(data.meta?.total ?? 0),
        last_page: Number(data.meta?.last_page ?? 1),
      },
    };
  },
  async get(id: string) {
    const { data } = await apiClient.get<ApiResponse<ParentGuardian>>(`/parents/${id}`);
    return unwrap(data);
  },
  async create(payload: Record<string, unknown>) {
    const { data } = await apiClient.post<ApiResponse<ParentGuardian>>('/parents', payload);
    return unwrap(data);
  },
  async update(id: string, payload: Record<string, unknown>) {
    const { data } = await apiClient.patch<ApiResponse<ParentGuardian>>(`/parents/${id}`, payload);
    return unwrap(data);
  },
  async archive(id: string) {
    const { data } = await apiClient.delete<ApiResponse<ParentGuardian>>(`/parents/${id}`);
    return unwrap(data);
  },
  async restore(id: string) {
    const { data } = await apiClient.post<ApiResponse<ParentGuardian>>(`/parents/${id}/restore`);
    return unwrap(data);
  },
  async attachChild(parentId: string, payload: { student_id: string; relationship: string; is_primary?: boolean; can_pickup?: boolean }) {
    const { data } = await apiClient.post<ApiResponse<ParentGuardian>>(`/parents/${parentId}/children`, payload);
    return unwrap(data);
  },
  async detachChild(parentId: string, studentId: string) {
    const { data } = await apiClient.delete<ApiResponse<ParentGuardian>>(`/parents/${parentId}/children/${studentId}`);
    return unwrap(data);
  },
  async statistics(): Promise<ParentStatistics> {
    const { data } = await apiClient.get<ApiResponse<ParentStatistics>>('/parents/statistics');
    return unwrap(data);
  },
};
