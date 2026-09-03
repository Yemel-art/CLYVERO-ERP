'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminApi, type AdminUser, type SchoolSettings } from '@/lib/api/admin';
import { useAuthStore } from '@/store/auth';

// Settings
export function useSettings() {
  return useQuery({ queryKey: ['admin', 'settings'], queryFn: () => adminApi.getSettings() });
}

export function useUpdateSchool() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<SchoolSettings>) => adminApi.updateSchool(payload),
    onSuccess: (school) => {
      const current = useAuthStore.getState().user;
      if (current?.school) useAuthStore.getState().updateUser({
        ...current,
        school: { ...current.school, name: school.name, slug: school.slug, school_code: school.school_code, logo_url: school.logo_url, default_locale: school.default_locale },
      });
      void qc.invalidateQueries({ queryKey: ['admin', 'settings'] });
    },
  });
}

export function useUploadSchoolLogo() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ file, kind }: { file: File; kind: 'primary' | 'secondary' | 'document_header' | 'student_id_stamp' }) =>
      adminApi.uploadSchoolLogo(file, kind),
    onSuccess: (logos) => {
      const current = useAuthStore.getState().user;
      if (current?.school) useAuthStore.getState().updateUser({
        ...current,
        school: { ...current.school, logo_url: logos.logo_url },
      });
      void qc.invalidateQueries({ queryKey: ['admin', 'settings'] });
    },
  });
}

// Users
export function useUsers(filters: { q?: string; role?: string; is_active?: boolean; page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['admin', 'users', filters],
    queryFn: () => adminApi.listUsers(filters),
    placeholderData: (p) => p,
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Parameters<typeof adminApi.createUser>[0]) => adminApi.createUser(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['admin', 'users'] }); },
  });
}

export function useUpdateUser(id: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<AdminUser> & { role?: string }) => adminApi.updateUser(id, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['admin', 'users'] }); },
  });
}

export function useResetUserPassword() {
  return useMutation({
    mutationFn: ({ id, password }: { id: string; password: string }) => adminApi.resetPassword(id, password),
  });
}

// Audit
export function useAuditLogs(filters: { module?: string; action?: string; from?: string; to?: string; page?: number; per_page?: number } = {}) {
  return useQuery({
    queryKey: ['admin', 'audit', filters],
    queryFn: () => adminApi.listAuditLogs(filters),
    placeholderData: (p) => p,
  });
}
