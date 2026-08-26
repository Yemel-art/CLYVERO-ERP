import { apiClient, unwrap } from './client';
import type { ApiResponse } from '@/types/api';
import type { Assessment, TermReport, ClassRanking } from '@/types/grades';

export interface GradeSheet {
  id: string;
  title: string;
  type: string;
  date: string;
  max_score: number;
  weight: number;
  status: 'draft' | 'published';
  subjects: Array<{ id: string; name: string; code: string; assessment_id: string }>;
  students: Array<{
    id: string;
    full_name: string;
    admission_number: string;
    scores: Record<string, number | null>;
  }>;
}

export const gradesApi = {
  async createGradeSheet(payload: {
    term_id: string; class_id: string; title: string; type: string; date: string;
    max_score: number; weight: number;
    subjects: Array<{ subject_id: string; entries: Array<{ student_id: string; score: number | null }> }>;
  }) {
    const { data } = await apiClient.post<ApiResponse<Assessment[]>>('/grades/grade-sheets', payload);
    return unwrap(data);
  },
  async listGradeSheets(classId: string, termId: string) {
    const { data } = await apiClient.get<ApiResponse<GradeSheet[]>>('/grades/grade-sheets', {
      params: { class_id: classId, term_id: termId },
    });
    return unwrap(data);
  },
  async updateGradeSheet(sheetId: string, scores: Array<{ assessment_id: string; student_id: string; score: number }>) {
    const { data } = await apiClient.put<ApiResponse<null>>(`/grades/grade-sheets/${sheetId}`, { scores });
    if (!data.success) throw new Error(data.message);
  },
  async listAssessments(params: { term_id?: string; class_id?: string; subject_id?: string; status?: string } = {}) {
    const { data } = await apiClient.get<ApiResponse<Assessment[]>>('/grades/assessments', { params });
    return unwrap(data);
  },
  async getAssessment(id: string) {
    const { data } = await apiClient.get<ApiResponse<Assessment>>(`/grades/assessments/${id}`);
    return unwrap(data);
  },
  async createAssessment(payload: Partial<Assessment>) {
    const { data } = await apiClient.post<ApiResponse<Assessment>>('/grades/assessments', payload);
    return unwrap(data);
  },
  async updateAssessment(id: string, payload: Partial<Assessment>) {
    const { data } = await apiClient.patch<ApiResponse<Assessment>>(`/grades/assessments/${id}`, payload);
    return unwrap(data);
  },
  async deleteAssessment(id: string) {
    const { data } = await apiClient.delete<ApiResponse<null>>(`/grades/assessments/${id}`);
    if (!data.success) throw new Error(data.message);
  },
  async publishAssessment(id: string) {
    const { data } = await apiClient.post<ApiResponse<Assessment>>(`/grades/assessments/${id}/publish`);
    return unwrap(data);
  },
  async recordEntries(id: string, entries: Array<{ student_id: string; score: number | null; comment?: string; grade_letter?: string }>) {
    const { data } = await apiClient.post<ApiResponse<Assessment>>(`/grades/assessments/${id}/entries`, { entries });
    return unwrap(data);
  },
  async termReport(studentId: string, termId: string): Promise<TermReport> {
    const { data } = await apiClient.get<ApiResponse<TermReport>>(`/grades/students/${studentId}/terms/${termId}/report`);
    return unwrap(data);
  },
  async classRanking(classId: string, termId: string): Promise<ClassRanking> {
    const { data } = await apiClient.get<ApiResponse<ClassRanking>>(`/grades/classes/${classId}/terms/${termId}/ranking`);
    return unwrap(data);
  },
};
