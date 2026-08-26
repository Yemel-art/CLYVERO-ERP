'use client';

import { useQuery } from '@tanstack/react-query';
import { dashboardApi } from '@/lib/api/dashboard';
import { useAuthStore } from '@/store/auth';

const DASHBOARD_STALE_TIME = 5 * 60 * 1000;

export const useAdminDashboard = () =>
  useQuery({ queryKey: ['dashboard', 'admin'], queryFn: () => dashboardApi.admin() });

export const useSecretaryDashboard = () =>
  useQuery({ queryKey: ['dashboard', 'secretary'], queryFn: () => dashboardApi.secretary() });

export const useTeacherDashboard = (enabled = true) => {
  const userId = useAuthStore((state) => state.user?.id);

  return useQuery({
    queryKey: ['dashboard', 'teacher', userId],
    queryFn: () => dashboardApi.teacher(),
    enabled: enabled && Boolean(userId),
    staleTime: DASHBOARD_STALE_TIME,
  });
};

export const useParentDashboard = () =>
  useQuery({ queryKey: ['dashboard', 'parent'], queryFn: () => dashboardApi.parent() });
