'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { timetableApi } from '@/lib/api/timetable';
import type { TimetableSlot } from '@/types/timetable';

export function useClassTimetable(classId: string | undefined) {
  return useQuery({
    queryKey: ['timetable', 'class', classId],
    queryFn: () => timetableApi.classSlots(classId as string),
    enabled: Boolean(classId),
  });
}

export function useTeacherTimetable(teacherId: string | undefined) {
  return useQuery({
    queryKey: ['timetable', 'teacher', teacherId],
    queryFn: () => timetableApi.teacherSlots(teacherId as string),
    enabled: Boolean(teacherId),
  });
}

export function useMyTimetable() {
  return useQuery({
    queryKey: ['timetable', 'mine'],
    queryFn: timetableApi.mySlots,
  });
}

export function useSchoolTimetable(academicYearId: string | undefined) {
  return useQuery({
    queryKey: ['timetable', 'school', academicYearId],
    queryFn: () => timetableApi.schoolSlots(academicYearId as string),
    enabled: Boolean(academicYearId),
  });
}

export function useCreateSlot() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<TimetableSlot>) => timetableApi.create(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['timetable'] }); },
  });
}

export function useUpdateSlot() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<TimetableSlot> }) => timetableApi.update(id, payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['timetable'] }); },
  });
}

export function useDeleteSlot() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => timetableApi.remove(id),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['timetable'] }); },
  });
}

export function useGenerationConfig(academicYearId: string | undefined) {
  return useQuery({
    queryKey: ['timetable', 'generation-config', academicYearId],
    queryFn: () => timetableApi.generationConfig(academicYearId as string),
    enabled: Boolean(academicYearId),
  });
}

export function useSaveGenerationConfig() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: timetableApi.saveGenerationConfig,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['timetable', 'generation-config'] });
    },
  });
}

export function useGenerateTimetable() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ academicYearId, replaceExisting }: { academicYearId: string; replaceExisting: boolean }) =>
      timetableApi.generate(academicYearId, replaceExisting),
    onSuccess: (result) => {
      if (result.generated) void qc.invalidateQueries({ queryKey: ['timetable'] });
    },
  });
}
