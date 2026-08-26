'use client';

import { useState, useEffect, useMemo } from 'react';
import { useParams, useSearchParams, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { ArrowLeft, Save, Lock, CheckCircle, XCircle, Clock, FileText } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
import { useOpenSession, useRecordAttendance, useCloseAttendanceSession } from '@/hooks/attendance';
import { useClass } from '@/hooks/academic';
import { studentsApi } from '@/lib/api/students';
import type { AttendanceStatus } from '@/types/attendance';
import { cn } from '@/lib/utils/cn';
import { useTranslation } from '@/hooks/useTranslation';
import { useAuthStore } from '@/store/auth';
import { dashboardRouteFor } from '@/types/user';

const STATUS: { value: AttendanceStatus; label: string; color: string; icon: typeof CheckCircle }[] = [
  { value: 'present', label: 'Present', color: 'success',   icon: CheckCircle },
  { value: 'absent',  label: 'Absent',  color: 'danger',    icon: XCircle },
  { value: 'late',    label: 'Late',    color: 'warning',   icon: Clock },
  { value: 'excused', label: 'Excused', color: 'info',      icon: FileText },
];

export default function TakeAttendancePage() {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const statusLabel = (status: AttendanceStatus, fallback: string) => language === 'fr'
    ? ({ present: 'Présent', absent: 'Absent', late: 'En retard', excused: 'Excusé' } as Record<AttendanceStatus, string>)[status]
    : fallback;
  const dashboardHref = user ? dashboardRouteFor(user.role.name) : '/login';
  const attendanceBase = user?.role.name === 'teacher'
    ? '/teacher/attendance'
    : user?.role.name === 'secretary'
      ? '/secretary/attendance'
      : '/admin/attendance';
  const { classId } = useParams<{ classId: string }>();
  const search = useSearchParams();
  const date = search.get('date') ?? new Date().toISOString().slice(0, 10);

  const { data: schoolClass } = useClass(classId);
  const { data: students } = useQuery({
    queryKey: ['students', 'by-class', classId],
    queryFn: () => studentsApi.list({ class_id: classId, per_page: 100 }),
    enabled: Boolean(classId),
  });

  const open = useOpenSession();
  const [sessionId, setSessionId] = useState<string | null>(null);
  const [statuses, setStatuses] = useState<Record<string, AttendanceStatus>>({});

  // Open the session (idempotent) on mount.
  useEffect(() => {
    if (!classId) return;
    open.mutateAsync({ class_id: classId, date }).then((s) => {
      setSessionId(s.id);
      const initial: Record<string, AttendanceStatus> = {};
      (s.records ?? []).forEach((r) => { initial[r.student_id] = r.status; });
      setStatuses(initial);
    }).catch(() => toast.error(ui('Impossible d’ouvrir la session de présence.', 'Could not open attendance session.')));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classId, date]);

  const record = useRecordAttendance(sessionId ?? '');
  const close = useCloseAttendanceSession();

  const setAll = (status: AttendanceStatus) => {
    if (!students) return;
    const next: Record<string, AttendanceStatus> = {};
    students.data.forEach((s) => { next[s.id] = status; });
    setStatuses(next);
  };

  const submit = async () => {
    if (!sessionId) return;
    const entries = Object.entries(statuses).map(([student_id, status]) => ({ student_id, status }));
    if (entries.length === 0) { toast.error(ui('Aucune présence n’a encore été marquée.', 'No attendance marked yet.')); return; }
    try {
      await record.mutateAsync(entries);
      toast.success(ui('Présences enregistrées.', 'Attendance saved.'));
    } catch {
      toast.error(ui('Impossible d’enregistrer les présences.', 'Could not save attendance.'));
    }
  };

  const closeSession = async () => {
    if (!sessionId) return;
    if (!window.confirm(ui('Clôturer cette session de présence ? Elle ne pourra plus être modifiée.', 'Close this attendance session? Once closed it cannot be edited.'))) return;
    await submit();
    try {
      await close.mutateAsync(sessionId);
      toast.success(ui('Session clôturée.', 'Session closed.'));
      router.push(attendanceBase);
    } catch {
      toast.error(ui('Impossible de clôturer la session.', 'Could not close session.'));
    }
  };

  const stats = useMemo(() => {
    const c = { present: 0, absent: 0, late: 0, excused: 0 } as Record<AttendanceStatus, number>;
    Object.values(statuses).forEach((s) => { c[s] += 1; });
    return c;
  }, [statuses]);

  if (!schoolClass) return <div className="space-y-4"><Skeleton className="h-8 w-1/3" /><Skeleton className="h-64" /></div>;

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: ui('Accueil', 'Home'), href: dashboardHref },
          { label: ui('Présences', 'Attendance'), href: attendanceBase },
          { label: `${schoolClass.name} · ${date}` },
        ]}
        title={`${ui('Présences', 'Attendance')} · ${schoolClass.name}`}
        description={`${date} · ${students?.data.length ?? 0} ${ui('élèves', 'students')}`}
        actions={<>
          <Button variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />} onClick={() => router.push(attendanceBase)}>{ui('Retour', 'Back')}</Button>
          <Button leftIcon={<Save className="h-4 w-4" />} onClick={submit} isLoading={record.isPending}>{ui('Enregistrer', 'Save')}</Button>
          <Button variant="danger" leftIcon={<Lock className="h-4 w-4" />} onClick={closeSession}>{ui('Enregistrer et clôturer', 'Save & close')}</Button>
        </>}
      />

      <div className="mb-4 grid grid-cols-4 gap-3">
        {STATUS.map((s) => (
          <Card key={s.value}><CardContent className="py-3">
            <p className="text-xs text-secondary-500">{statusLabel(s.value, s.label)}</p>
            <p className="text-xl font-semibold text-ink">{stats[s.value]}</p>
          </CardContent></Card>
        ))}
      </div>

      <Card className="mb-4">
        <CardContent className="flex flex-wrap items-center gap-2 py-3">
          <span className="mr-2 text-sm text-secondary-500">{ui('Tout marquer', 'Mark all')} :</span>
          {STATUS.map((s) => (
            <Button key={s.value} size="sm" variant="outline" onClick={() => setAll(s.value)}>
              {statusLabel(s.value, s.label)}
            </Button>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="py-2">
          {(students?.data ?? []).map((s) => {
            const current = statuses[s.id];
            return (
              <div key={s.id} className="flex items-center gap-3 border-b border-secondary-100 py-3 last:border-0">
                {s.photo_url
                  ? <img src={s.photo_url} alt="" className="h-10 w-10 rounded-full object-cover" />
                  : <Avatar name={s.full_name} size="sm" />}
                <div className="flex-1">
                  <p className="font-medium text-ink">{s.full_name}</p>
                  <p className="text-xs text-secondary-500">{s.admission_number}</p>
                </div>
                <div className="flex gap-1">
                  {STATUS.map((opt) => {
                    const selected = current === opt.value;
                    const Icon = opt.icon;
                    return (
                      <button key={opt.value} onClick={() => setStatuses((x) => ({ ...x, [s.id]: opt.value }))}
                        aria-label={statusLabel(opt.value, opt.label)}
                        className={cn(
                          'rounded-button border px-3 py-1.5 text-xs font-medium transition-colors flex items-center gap-1',
                          selected
                            ? opt.value === 'present' ? 'border-success bg-success-light text-success'
                              : opt.value === 'absent' ? 'border-danger bg-danger-light text-danger'
                              : opt.value === 'late' ? 'border-warning bg-warning-light text-warning'
                              : 'border-info bg-info-light text-info'
                            : 'border-secondary-200 text-secondary-600 hover:bg-secondary-50'
                        )}>
                        <Icon className="h-3.5 w-3.5" />
                        {statusLabel(opt.value, opt.label)}
                      </button>
                    );
                  })}
                </div>
              </div>
            );
          })}
          {(students?.data ?? []).length === 0 && (
            <p className="py-8 text-center text-sm text-secondary-500">{ui('Aucun élève inscrit dans cette classe.', 'No students enrolled in this class yet.')}</p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
