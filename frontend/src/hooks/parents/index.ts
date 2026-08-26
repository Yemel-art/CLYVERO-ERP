'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { parentsApi } from '@/lib/api/parents';
import type { ParentGuardian, ParentFilters } from '@/types/parent';

const KEYS = {
  all: ['parents'] as const,
  list: (filters: ParentFilters) => ['parents', 'list', filters] as const,
  detail: (id: string) => ['parents', 'detail', id] as const,
  stats: () => ['parents', 'statistics'] as const,
};

export function useParents(filters: ParentFilters, enabled = true) {
  return useQuery({ queryKey: KEYS.list(filters), queryFn: () => parentsApi.list(filters), placeholderData: (previous) => previous, enabled });
}
export function useParent(id: string | undefined) {
  return useQuery({ queryKey: KEYS.detail(id ?? ''), queryFn: () => parentsApi.get(id as string), enabled: Boolean(id) });
}
export function useParentStatistics() {
  return useQuery({ queryKey: KEYS.stats(), queryFn: () => parentsApi.statistics() });
}
export function useCreateParent() {
  const client = useQueryClient();
  return useMutation({ mutationFn: (payload: Record<string, unknown>) => parentsApi.create(payload), onSuccess: () => { void client.invalidateQueries({ queryKey: KEYS.all }); } });
}
export function useUpdateParent(id: string) {
  const client = useQueryClient();
  return useMutation({ mutationFn: (payload: Record<string, unknown>) => parentsApi.update(id, payload), onSuccess: (parent: ParentGuardian) => { client.setQueryData(KEYS.detail(id), parent); void client.invalidateQueries({ queryKey: KEYS.all }); } });
}
export function useArchiveParent() {
  const client = useQueryClient();
  return useMutation({ mutationFn: (id: string) => parentsApi.archive(id), onSuccess: () => { void client.invalidateQueries({ queryKey: KEYS.all }); } });
}
export function useRestoreParent() {
  const client = useQueryClient();
  return useMutation({ mutationFn: (id: string) => parentsApi.restore(id), onSuccess: () => { void client.invalidateQueries({ queryKey: KEYS.all }); } });
}
export function useAttachChild(parentId: string) {
  const client = useQueryClient();
  return useMutation({ mutationFn: (payload: { student_id: string; relationship: string; is_primary?: boolean; can_pickup?: boolean }) => parentsApi.attachChild(parentId, payload), onSuccess: (parent: ParentGuardian) => { client.setQueryData(KEYS.detail(parentId), parent); void client.invalidateQueries({ queryKey: KEYS.all }); } });
}
export function useDetachChild(parentId: string) {
  const client = useQueryClient();
  return useMutation({ mutationFn: (studentId: string) => parentsApi.detachChild(parentId, studentId), onSuccess: (parent: ParentGuardian) => { client.setQueryData(KEYS.detail(parentId), parent); void client.invalidateQueries({ queryKey: KEYS.all }); } });
}
