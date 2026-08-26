'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { teachersApi } from '@/lib/api/teachers';
import type { Teacher, TeacherFilters } from '@/types/teacher';

const KEYS = {
  all: ['teachers'] as const,
  list: (f: TeacherFilters) => ['teachers', 'list', f] as const,
  detail: (id: string) => ['teachers', 'detail', id] as const,
  stats: () => ['teachers', 'statistics'] as const,
};

export function useTeachers(filters: TeacherFilters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () => teachersApi.list(filters),
    placeholderData: (p) => p,
  });
}

export function useTeacher(id: string | undefined) {
  return useQuery({
    queryKey: KEYS.detail(id ?? ''),
    queryFn: () => teachersApi.get(id as string),
    enabled: Boolean(id),
  });
}

export function useTeacherStatistics() {
  return useQuery({ queryKey: KEYS.stats(), queryFn: () => teachersApi.statistics() });
}

export function useCreateTeacher() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => teachersApi.create(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: KEYS.all });
      void qc.invalidateQueries({ queryKey: ['admin', 'users'] });
    },
  });
}

export function useUpdateTeacher(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) => teachersApi.update(id, payload),
    onSuccess: (updated: Teacher) => {
      qc.setQueryData<Teacher>(KEYS.detail(id), updated);
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}

export function useArchiveTeacher() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => teachersApi.archive(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: KEYS.all }); },
  });
}

export function useRestoreTeacher() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => teachersApi.restore(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: KEYS.all }); },
  });
}

export function useUploadTeacherPhoto(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (file: File) => teachersApi.uploadPhoto(id, file),
    onSuccess: (updated: Teacher) => {
      qc.setQueryData<Teacher>(KEYS.detail(id), updated);
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}

export function useRemoveTeacherPhoto(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => teachersApi.removePhoto(id),
    onSuccess: (updated: Teacher) => {
      qc.setQueryData<Teacher>(KEYS.detail(id), updated);
      void qc.invalidateQueries({ queryKey: KEYS.all });
    },
  });
}
