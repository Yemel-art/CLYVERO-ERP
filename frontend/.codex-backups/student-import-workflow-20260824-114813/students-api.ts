import { apiClient, unwrap } from './client';
import type { ApiResponse, ApiMeta } from '@/types/api';
import type { Student, StudentAcademicHistory, StudentFilters, StudentImport, StudentImportAnalysis, StudentStatistics } from '@/types/student';

interface PaginatedStudents {
  data: Student[];
  meta: Required<Pick<ApiMeta, 'page' | 'per_page' | 'total' | 'last_page'>>;
}

function toQuery(filters: StudentFilters): Record<string, string | number | undefined> {
  const params: Record<string, string | number | undefined> = {
    page: filters.page, per_page: filters.per_page, sort: filters.sort, order: filters.order,
  };
  if (filters.q) params.q = filters.q;
  if (filters.status) params.status = filters.status;
  if (filters.gender) params.gender = filters.gender;
  if (filters.cycle) params.cycle = filters.cycle;
  if (filters.class_id) params.class_id = filters.class_id;
  if (filters.academic_year_id) params.academic_year_id = filters.academic_year_id;
  if (filters.include_archived) params.include_archived = 1;
  return params;
}

export const studentsApi = {
  async list(filters: StudentFilters = {}): Promise<PaginatedStudents> {
    const { data } = await apiClient.get<ApiResponse<Student[]>>('/students', { params: toQuery(filters) });
    if (!data.success) throw new Error(data.message);
    return { data: data.data ?? [], meta: {
      page: Number(data.meta?.page ?? 1), per_page: Number(data.meta?.per_page ?? 20),
      total: Number(data.meta?.total ?? 0), last_page: Number(data.meta?.last_page ?? 1),
    }};
  },
  async get(id: string): Promise<Student> {
    const { data } = await apiClient.get<ApiResponse<Student>>(`/students/${id}`);
    return unwrap(data);
  },
  async academicHistory(id: string): Promise<StudentAcademicHistory[]> {
    const { data } = await apiClient.get<ApiResponse<StudentAcademicHistory[]>>(`/students/${id}/academic-history`);
    return unwrap(data);
  },
  async create(payload: Record<string, unknown>): Promise<Student> {
    const { data } = await apiClient.post<ApiResponse<Student>>('/students', payload);
    return unwrap(data);
  },
  async update(id: string, payload: Record<string, unknown>): Promise<Student> {
    const { data } = await apiClient.patch<ApiResponse<Student>>(`/students/${id}`, payload);
    return unwrap(data);
  },
  async archive(id: string): Promise<Student> {
    const { data } = await apiClient.delete<ApiResponse<Student>>(`/students/${id}`);
    return unwrap(data);
  },
  async restore(id: string): Promise<Student> {
    const { data } = await apiClient.post<ApiResponse<Student>>(`/students/${id}/restore`);
    return unwrap(data);
  },
  async permanentlyDelete(id: string, confirmation: string, reason: 'test_record' | 'duplicate_record' | 'registration_error'): Promise<void> {
    const { data } = await apiClient.delete<ApiResponse<null>>(`/students/${id}/permanent`, {
      data: { confirmation, reason, acknowledge_permanent: true },
    });
    if (!data.success) throw new Error(data.message);
  },
  async uploadPhoto(id: string, file: File): Promise<Student> {
    const body = new FormData(); body.append('photo', file);
    const { data } = await apiClient.post<ApiResponse<Student>>(`/students/${id}/photo`, body);
    return unwrap(data);
  },
  async removePhoto(id: string): Promise<Student> {
    const { data } = await apiClient.delete<ApiResponse<Student>>(`/students/${id}/photo`);
    return unwrap(data);
  },
  async statistics(): Promise<StudentStatistics> {
    const { data } = await apiClient.get<ApiResponse<StudentStatistics>>('/students/statistics');
    return unwrap(data);
  },
  async analyzeOfficialImport(file: File): Promise<StudentImportAnalysis> {
    const body = new FormData();
    body.append('file', file);
    const { data } = await apiClient.post<ApiResponse<StudentImportAnalysis>>('/students/imports/analyze', body);
    return unwrap(data);
  },
  async previewOfficialImport(file: File, columnMapping: Record<string, string>): Promise<StudentImport> {
    const body = new FormData();
    body.append('file', file);
    Object.entries(columnMapping).forEach(([field, column]) => { if (column) body.append(`column_mapping[${field}]`, column); });
    const { data } = await apiClient.post<ApiResponse<StudentImport>>('/students/imports/preview', body);
    return unwrap(data);
  },
  async confirmOfficialImport(id: string, mapping: Array<{ source_class: string; class_id: string }>, duplicateAction: 'skip' | 'update'): Promise<StudentImport> {
    const { data } = await apiClient.post<ApiResponse<StudentImport>>(`/students/imports/${id}/confirm`, { class_mapping: mapping, duplicate_action: duplicateAction });
    return unwrap(data);
  },
};
