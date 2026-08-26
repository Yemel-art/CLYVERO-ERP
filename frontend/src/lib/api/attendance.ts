import { apiClient, unwrap } from './client';
import type { ApiResponse } from '@/types/api';
import type { AttendanceSession, AttendanceStats, StudentAttendanceSummary, AttendanceStatus } from '@/types/attendance';

export const attendanceApi = {
  async listSessions(params: { class_id?: string; from?: string; to?: string } = {}) {
    const { data } = await apiClient.get<ApiResponse<AttendanceSession[]>>('/attendance/sessions', { params });
    return unwrap(data);
  },
  async openSession(payload: { class_id: string; date: string; period?: string }) {
    const { data } = await apiClient.post<ApiResponse<AttendanceSession>>('/attendance/sessions', payload);
    return unwrap(data);
  },
  async getSession(id: string) {
    const { data } = await apiClient.get<ApiResponse<AttendanceSession>>(`/attendance/sessions/${id}`);
    return unwrap(data);
  },
  async recordBulk(id: string, entries: Array<{ student_id: string; status: AttendanceStatus; notes?: string }>) {
    const { data } = await apiClient.post<ApiResponse<AttendanceSession>>(`/attendance/sessions/${id}/records`, { entries });
    return unwrap(data);
  },
  async closeSession(id: string) {
    const { data } = await apiClient.post<ApiResponse<AttendanceSession>>(`/attendance/sessions/${id}/close`);
    return unwrap(data);
  },
  async stats(id: string): Promise<AttendanceStats> {
    const { data } = await apiClient.get<ApiResponse<AttendanceStats>>(`/attendance/sessions/${id}/stats`);
    return unwrap(data);
  },
  async studentSummary(studentId: string, termId?: string): Promise<StudentAttendanceSummary> {
    const { data } = await apiClient.get<ApiResponse<StudentAttendanceSummary>>(`/attendance/students/${studentId}/summary`, { params: termId ? { term_id: termId } : {} });
    return unwrap(data);
  },
};
