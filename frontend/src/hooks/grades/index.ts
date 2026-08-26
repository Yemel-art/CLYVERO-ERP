'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { gradesApi } from '@/lib/api/grades';
import type { Assessment } from '@/types/grades';

export function useAssessments(params: { term_id?: string; class_id?: string; subject_id?: string; status?: string } = {}, enabled = true) {
  return useQuery({
    queryKey: ['grades', 'assessments', params],
    queryFn: () => gradesApi.listAssessments(params),
    enabled,
  });
}

export function useAssessment(id: string | undefined) {
  return useQuery({
    queryKey: ['grades', 'assessments', 'detail', id],
    queryFn: () => gradesApi.getAssessment(id as string),
    enabled: Boolean(id),
  });
}

export function useCreateAssessment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<Assessment>) => gradesApi.createAssessment(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['grades'] });
      void qc.invalidateQueries({ queryKey: ['academic', 'classes'] });
    },
  });
}

export function useCreateGradeSheet() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Parameters<typeof gradesApi.createGradeSheet>[0]) => gradesApi.createGradeSheet(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['grades'] });
      void qc.invalidateQueries({ queryKey: ['academic', 'classes'] });
    },
  });
}

export function useGradeSheets(classId: string | undefined, termId: string | undefined) {
  return useQuery({
    queryKey: ['grades', 'sheets', classId, termId],
    queryFn: () => gradesApi.listGradeSheets(classId as string, termId as string),
    enabled: Boolean(classId && termId),
    staleTime: 2 * 60 * 1000,
  });
}

export function useUpdateGradeSheet() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ sheetId, scores }: {
      sheetId: string;
      scores: Array<{ assessment_id: string; student_id: string; score: number }>;
    }) => gradesApi.updateGradeSheet(sheetId, scores),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['grades'] }); },
  });
}

export function useRecordEntries(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (entries: Array<{ student_id: string; score: number | null; comment?: string }>) =>
      gradesApi.recordEntries(id, entries),
    onSuccess: (a: Assessment) => {
      qc.setQueryData(['grades', 'assessments', 'detail', id], a);
    },
  });
}

export function usePublishAssessment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => gradesApi.publishAssessment(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['grades'] }); },
  });
}

export function useDeleteAssessment() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => gradesApi.deleteAssessment(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['grades'] }); },
  });
}

export function useTermReport(studentId: string | undefined, termId: string | undefined) {
  return useQuery({
    queryKey: ['grades', 'report', studentId, termId],
    queryFn: () => gradesApi.termReport(studentId as string, termId as string),
    enabled: Boolean(studentId && termId),
  });
}

export function useClassRanking(classId: string | undefined, termId: string | undefined, enabled = true) {
  return useQuery({
    queryKey: ['grades', 'ranking', classId, termId],
    queryFn: () => gradesApi.classRanking(classId as string, termId as string),
    enabled: enabled && Boolean(classId && termId),
  });
}
