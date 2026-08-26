'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { studentsApi } from '@/lib/api/students';
import type { Student, StudentFilters, StudentStatistics } from '@/types/student';

const KEYS = {
  all: ['students'] as const,
  list: (filters: StudentFilters) => ['students', 'list', filters] as const,
  detail: (id: string) => ['students', 'detail', id] as const,
  history: (id: string) => ['students', 'history', id] as const,
  stats: () => ['students', 'statistics'] as const,
};

/** Paginated student list. */
export function useStudents(filters: StudentFilters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () => studentsApi.list(filters),
    placeholderData: (previous) => previous,
  });
}

/** Single student profile. */
export function useStudent(id: string | undefined) {
  return useQuery({
    queryKey: KEYS.detail(id ?? ''),
    queryFn: () => studentsApi.get(id as string),
    enabled: Boolean(id),
  });
}

export function useStudentAcademicHistory(id: string | undefined) {
  return useQuery({
    queryKey: KEYS.history(id ?? ''),
    queryFn: () => studentsApi.academicHistory(id as string),
    enabled: Boolean(id),
  });
}

/** Statistics card data. */
export function useStudentStatistics() {
  return useQuery<StudentStatistics>({
    queryKey: KEYS.stats(),
    queryFn: () => studentsApi.statistics(),
  });
}

/** Create. */
export function useCreateStudent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => studentsApi.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}

/** Update. */
export function useUpdateStudent(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => studentsApi.update(id, payload),
    onSuccess: (updated: Student) => {
      qc.setQueryData<Student>(KEYS.detail(id), updated);
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}

/** Archive (soft delete). */
export function useArchiveStudent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => studentsApi.archive(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}

/** Restore from archive. */
export function useRestoreStudent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => studentsApi.restore(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}

/** Irreversible administrator-only deletion. */
export function usePermanentlyDeleteStudent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, confirmation, reason = 'registration_error' }: { id: string; confirmation: string; reason?: 'test_record' | 'duplicate_record' | 'registration_error' }) => studentsApi.permanentlyDelete(id, confirmation, reason),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: KEYS.all }); },
  });
}

/** Upload photo. */
export function useUploadStudentPhoto(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (file: File) => studentsApi.uploadPhoto(id, file),
    onSuccess: (updated: Student) => {
      qc.setQueryData<Student>(KEYS.detail(id), updated);
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}

/** Remove photo. */
export function useRemoveStudentPhoto(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => studentsApi.removePhoto(id),
    onSuccess: (updated: Student) => {
      qc.setQueryData<Student>(KEYS.detail(id), updated);
    },
  });
}
