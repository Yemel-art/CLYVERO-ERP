'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { academicApi } from '@/lib/api/academic';
import type { AcademicYear, Term, Subject, SchoolClass, PromotionPolicy, AcademicDecision } from '@/types/academic';

const ACADEMIC_STRUCTURE_STALE_TIME = 5 * 60 * 1000;

// ─── Years ─────────────────────────────────────────────────────────
export function useAcademicYears() {
  return useQuery({
    queryKey: ['academic', 'years'],
    queryFn: () => academicApi.listYears(),
    staleTime: ACADEMIC_STRUCTURE_STALE_TIME,
  });
}

export function useCreateYear() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<AcademicYear>) => academicApi.createYear(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'years'] }); },
  });
}

export function useActivateYear() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => academicApi.activateYear(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic'] }); },
  });
}

export function usePromotionPolicy(yearId: string | undefined) {
  return useQuery({
    queryKey: ['academic', 'promotion-policy', yearId],
    queryFn: () => academicApi.getPromotionPolicy(yearId as string),
    enabled: Boolean(yearId),
  });
}

export function useSavePromotionPolicy(yearId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Omit<PromotionPolicy, 'id' | 'academic_year_id' | 'is_active'>) =>
      academicApi.savePromotionPolicy(yearId, payload),
    onSuccess: (policy) => qc.setQueryData(['academic', 'promotion-policy', yearId], policy),
  });
}

export function useAcademicDecisions(yearId: string | undefined) {
  return useQuery({
    queryKey: ['academic', 'decisions', yearId],
    queryFn: () => academicApi.listAcademicDecisions(yearId as string),
    enabled: Boolean(yearId),
  });
}

export function useEvaluateAcademicDecisions(yearId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => academicApi.evaluateAcademicDecisions(yearId),
    onSuccess: (decisions) => qc.setQueryData(['academic', 'decisions', yearId], decisions),
  });
}

export function useUpdateAcademicDecision(yearId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<Pick<AcademicDecision, 'final_decision' | 'teacher_appreciation' | 'class_council_recommendation' | 'override_reason'>> }) =>
      academicApi.updateAcademicDecision(id, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'decisions', yearId] }); },
  });
}

// ─── Terms ─────────────────────────────────────────────────────────
export function useTerms(yearId: string | undefined) {
  return useQuery({
    queryKey: ['academic', 'terms', yearId],
    queryFn: () => academicApi.listTerms(yearId as string),
    enabled: Boolean(yearId),
    staleTime: ACADEMIC_STRUCTURE_STALE_TIME,
  });
}

export function useCreateTerm(yearId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<Term>) => academicApi.createTerm(yearId, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'terms', yearId] }); },
  });
}

export function useActivateTerm() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => academicApi.activateTerm(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'terms'] }); },
  });
}

export function useCloseTerm() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => academicApi.closeTerm(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'terms'] }); },
  });
}

// ─── Subjects ──────────────────────────────────────────────────────
export function useSubjects(params: { q?: string; is_active?: boolean; include_archived?: boolean; education_system?: 'secondary_general' | 'secondary_technical' } = {}) {
  return useQuery({ queryKey: ['academic', 'subjects', params], queryFn: () => academicApi.listSubjects(params) });
}

export function useSubject(id: string | undefined) {
  return useQuery({
    queryKey: ['academic', 'subjects', 'detail', id],
    queryFn: () => academicApi.getSubject(id as string),
    enabled: Boolean(id),
  });
}

export function useCreateSubject() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<Subject>) => academicApi.createSubject(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'subjects'] }); },
  });
}

export function useUpdateSubject(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<Subject>) => academicApi.updateSubject(id, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'subjects'] }); },
  });
}

export function useArchiveSubject() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => academicApi.archiveSubject(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'subjects'] }); },
  });
}

export function useRestoreSubject() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => academicApi.restoreSubject(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'subjects'] }); },
  });
}

// ─── Classes ───────────────────────────────────────────────────────
export function useClasses(params: { q?: string; academic_year_id?: string; include_archived?: boolean; per_page?: number } = {}) {
  const requestParams = { per_page: 200, ...params };
  return useQuery({
    queryKey: ['academic', 'classes', requestParams],
    queryFn: () => academicApi.listClasses(requestParams),
  });
}

export function useClass(id: string | undefined) {
  return useQuery({
    queryKey: ['academic', 'classes', 'detail', id],
    queryFn: () => academicApi.getClass(id as string),
    enabled: Boolean(id),
    // A class detail contains its live student roster. Never reuse a stale
    // roster after registration, import, transfer, archive, or restoration.
    staleTime: 0,
    refetchOnMount: 'always',
  });
}

export function useCreateClass() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<SchoolClass>) => academicApi.createClass(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'classes'] }); },
  });
}

export function useUpdateClass(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<SchoolClass>) => academicApi.updateClass(id, payload),
    onSuccess: (c: SchoolClass) => {
      qc.setQueryData(['academic', 'classes', 'detail', id], c);
      void qc.invalidateQueries({ queryKey: ['academic', 'classes'] });
    },
  });
}

export function useArchiveClass() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => academicApi.archiveClass(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'classes'] }); },
  });
}

export function useRestoreClass() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => academicApi.restoreClass(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['academic', 'classes'] }); },
  });
}

export function useAttachSubjectToClass(classId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { subject_id: string; teacher_id?: string | null; coefficient?: number; weekly_frequency?: number }) =>
      academicApi.attachSubject(classId, payload),
    onSuccess: (c: SchoolClass) => {
      qc.setQueryData(['academic', 'classes', 'detail', classId], c);
    },
  });
}

export function useDetachSubjectFromClass(classId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (subjectId: string) => academicApi.detachSubject(classId, subjectId),
    onSuccess: (c: SchoolClass) => {
      qc.setQueryData(['academic', 'classes', 'detail', classId], c);
    },
  });
}
