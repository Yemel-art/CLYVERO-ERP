'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { attendanceApi } from '@/lib/api/attendance';
import type { AttendanceSession, AttendanceStatus } from '@/types/attendance';

export function useAttendanceSessions(params: { class_id?: string; from?: string; to?: string } = {}) {
  return useQuery({ queryKey: ['attendance', 'sessions', params], queryFn: () => attendanceApi.listSessions(params) });
}

export function useAttendanceSession(id: string | undefined) {
  return useQuery({
    queryKey: ['attendance', 'sessions', 'detail', id],
    queryFn: () => attendanceApi.getSession(id as string),
    enabled: Boolean(id),
  });
}

export function useStudentAttendanceSummary(studentId: string | undefined, termId?: string) {
  return useQuery({
    queryKey: ['attendance', 'student-summary', studentId, termId],
    queryFn: () => attendanceApi.studentSummary(studentId as string, termId),
    enabled: Boolean(studentId),
  });
}

export function useOpenSession() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { class_id: string; date: string; period?: string }) => attendanceApi.openSession(payload),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['attendance'] }); },
  });
}

export function useRecordAttendance(sessionId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (entries: Array<{ student_id: string; status: AttendanceStatus; notes?: string }>) =>
      attendanceApi.recordBulk(sessionId, entries),
    onSuccess: (s: AttendanceSession) => {
      qc.setQueryData(['attendance', 'sessions', 'detail', sessionId], s);
    },
  });
}

export function useCloseAttendanceSession() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => attendanceApi.closeSession(id),
    onSuccess: (s: AttendanceSession) => {
      qc.setQueryData(['attendance', 'sessions', 'detail', s.id], s);
      void qc.invalidateQueries({ queryKey: ['attendance', 'sessions'] });
    },
  });
}
