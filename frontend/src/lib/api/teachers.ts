import { apiClient, unwrap } from './client';
import type { ApiResponse, ApiMeta } from '@/types/api';
import type { CreateTeacherResult, Teacher, TeacherFilters, TeacherStatistics } from '@/types/teacher';

interface PaginatedTeachers {
  data: Teacher[];
  meta: Required<Pick<ApiMeta, 'page' | 'per_page' | 'total' | 'last_page'>>;
}

function toQuery(f: TeacherFilters) {
  const p: Record<string, string | number | undefined> = {
    page: f.page, per_page: f.per_page, sort: f.sort, order: f.order,
  };
  if (f.q) p.q = f.q;
  if (f.status) p.status = f.status;
  if (f.gender) p.gender = f.gender;
  if (f.include_archived) p.include_archived = 1;
  return p;
}

export const teachersApi = {
  async list(filters: TeacherFilters = {}): Promise<PaginatedTeachers> {
    const { data } = await apiClient.get<ApiResponse<Teacher[]>>('/teachers', { params: toQuery(filters) });
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
    const { data } = await apiClient.get<ApiResponse<Teacher>>(`/teachers/${id}`);
    return unwrap(data);
  },
  async create(payload: Record<string, unknown>) {
    const { data } = await apiClient.post<ApiResponse<CreateTeacherResult>>('/teachers', payload);
    return unwrap(data);
  },
  async update(id: string, payload: Record<string, unknown>) {
    const { data } = await apiClient.patch<ApiResponse<Teacher>>(`/teachers/${id}`, payload);
    return unwrap(data);
  },
  async archive(id: string) {
    const { data } = await apiClient.delete<ApiResponse<Teacher>>(`/teachers/${id}`);
    return unwrap(data);
  },
  async restore(id: string) {
    const { data } = await apiClient.post<ApiResponse<Teacher>>(`/teachers/${id}/restore`);
    return unwrap(data);
  },
  async uploadPhoto(id: string, file: File) {
    const fd = new FormData();
    fd.append('photo', file);
    const { data } = await apiClient.post<ApiResponse<Teacher>>(`/teachers/${id}/photo`, fd);
    return unwrap(data);
  },
  async removePhoto(id: string) {
    const { data } = await apiClient.delete<ApiResponse<Teacher>>(`/teachers/${id}/photo`);
    return unwrap(data);
  },
  async statistics(): Promise<TeacherStatistics> {
    const { data } = await apiClient.get<ApiResponse<TeacherStatistics>>('/teachers/statistics');
    return unwrap(data);
  },
};
