import { apiClient, unwrap } from './client';
import type { ApiResponse } from '@/types/api';
import type { AcademicYear, Term, Subject, SchoolClass, PromotionPolicy, AcademicDecision } from '@/types/academic';

export const academicApi = {
  // ─── Academic Years ─────────────────────────────────────────────
  async listYears() {
    const { data } = await apiClient.get<ApiResponse<AcademicYear[]>>('/academic-years');
    return unwrap(data);
  },
  async createYear(payload: Partial<AcademicYear>) {
    const { data } = await apiClient.post<ApiResponse<AcademicYear>>('/academic-years', payload);
    return unwrap(data);
  },
  async activateYear(id: string) {
    const { data } = await apiClient.post<ApiResponse<AcademicYear>>(`/academic-years/${id}/activate`);
    return unwrap(data);
  },
  async getPromotionPolicy(yearId: string) {
    const { data } = await apiClient.get<ApiResponse<PromotionPolicy | null>>(`/academic-years/${yearId}/promotion-policy`);
    return unwrap(data);
  },
  async savePromotionPolicy(yearId: string, payload: Omit<PromotionPolicy, 'id' | 'academic_year_id' | 'is_active'>) {
    const { data } = await apiClient.put<ApiResponse<PromotionPolicy>>(`/academic-years/${yearId}/promotion-policy`, payload);
    return unwrap(data);
  },
  async listAcademicDecisions(yearId: string) {
    const { data } = await apiClient.get<ApiResponse<AcademicDecision[]>>(`/academic-years/${yearId}/academic-decisions`);
    return unwrap(data);
  },
  async evaluateAcademicDecisions(yearId: string) {
    const { data } = await apiClient.post<ApiResponse<AcademicDecision[]>>(`/academic-years/${yearId}/academic-decisions/evaluate`);
    return unwrap(data);
  },
  async updateAcademicDecision(id: string, payload: Partial<Pick<AcademicDecision, 'final_decision' | 'teacher_appreciation' | 'class_council_recommendation' | 'override_reason'>>) {
    const { data } = await apiClient.patch<ApiResponse<AcademicDecision>>(`/academic-decisions/${id}`, payload);
    return unwrap(data);
  },

  // ─── Terms ──────────────────────────────────────────────────────
  async listTerms(yearId: string) {
    const { data } = await apiClient.get<ApiResponse<Term[]>>(`/academic-years/${yearId}/terms`);
    return unwrap(data);
  },
  async createTerm(yearId: string, payload: Partial<Term>) {
    const { data } = await apiClient.post<ApiResponse<Term>>(`/academic-years/${yearId}/terms`, payload);
    return unwrap(data);
  },
  async activateTerm(id: string) {
    const { data } = await apiClient.post<ApiResponse<Term>>(`/terms/${id}/activate`);
    return unwrap(data);
  },
  async closeTerm(id: string) {
    const { data } = await apiClient.post<ApiResponse<Term>>(`/terms/${id}/close`);
    return unwrap(data);
  },

  // ─── Subjects ───────────────────────────────────────────────────
  async listSubjects(params: { q?: string; is_active?: boolean; include_archived?: boolean; education_system?: 'secondary_general' | 'secondary_technical'; per_page?: number } = {}) {
    const { data } = await apiClient.get<ApiResponse<Subject[]>>('/subjects', { params });
    return unwrap(data);
  },
  async getSubject(id: string) {
    const { data } = await apiClient.get<ApiResponse<Subject>>(`/subjects/${id}`);
    return unwrap(data);
  },
  async createSubject(payload: Partial<Subject>) {
    const { data } = await apiClient.post<ApiResponse<Subject>>('/subjects', payload);
    return unwrap(data);
  },
  async updateSubject(id: string, payload: Partial<Subject>) {
    const { data } = await apiClient.patch<ApiResponse<Subject>>(`/subjects/${id}`, payload);
    return unwrap(data);
  },
  async archiveSubject(id: string) {
    const { data } = await apiClient.delete<ApiResponse<Subject>>(`/subjects/${id}`);
    return unwrap(data);
  },
  async restoreSubject(id: string) {
    const { data } = await apiClient.post<ApiResponse<Subject>>(`/subjects/${id}/restore`);
    return unwrap(data);
  },

  // ─── Classes ────────────────────────────────────────────────────
  async listClasses(params: { q?: string; academic_year_id?: string; include_archived?: boolean; per_page?: number } = {}) {
    const { data } = await apiClient.get<ApiResponse<SchoolClass[]>>('/classes', { params });
    return unwrap(data);
  },
  async getClass(id: string) {
    const { data } = await apiClient.get<ApiResponse<SchoolClass>>(`/classes/${id}`);
    return unwrap(data);
  },
  async createClass(payload: Partial<SchoolClass>) {
    const { data } = await apiClient.post<ApiResponse<SchoolClass>>('/classes', payload);
    return unwrap(data);
  },
  async updateClass(id: string, payload: Partial<SchoolClass>) {
    const { data } = await apiClient.patch<ApiResponse<SchoolClass>>(`/classes/${id}`, payload);
    return unwrap(data);
  },
  async archiveClass(id: string) {
    const { data } = await apiClient.delete<ApiResponse<SchoolClass>>(`/classes/${id}`);
    return unwrap(data);
  },
  async restoreClass(id: string) {
    const { data } = await apiClient.post<ApiResponse<SchoolClass>>(`/classes/${id}/restore`);
    return unwrap(data);
  },
  async attachSubject(classId: string, payload: { subject_id: string; teacher_id?: string | null; coefficient?: number; weekly_frequency?: number }) {
    const { data } = await apiClient.post<ApiResponse<SchoolClass>>(`/classes/${classId}/subjects`, payload);
    return unwrap(data);
  },
  async detachSubject(classId: string, subjectId: string) {
    const { data } = await apiClient.delete<ApiResponse<SchoolClass>>(`/classes/${classId}/subjects/${subjectId}`);
    return unwrap(data);
  },
};
