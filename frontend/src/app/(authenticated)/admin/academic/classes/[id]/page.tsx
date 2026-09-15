'use client';

import { useState, useMemo } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { ArrowLeft, Download, Plus, X } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { useClass, useAttachSubjectToClass, useDetachSubjectFromClass, useSubjects } from '@/hooks/academic';
import { teachersApi } from '@/lib/api/teachers';
import { useAuthStore } from '@/store/auth';
import { dashboardRouteFor } from '@/types/user';

export default function ClassDetailPage() {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const hasPermission = useAuthStore((state) => state.hasPermission);
  const isSecretary = user?.role.name === 'secretary';
  const classesBase = isSecretary ? '/secretary/classes' : '/admin/academic/classes';
  const studentsBase = isSecretary ? '/secretary/students' : '/admin/students';
  const dashboardHref = user ? dashboardRouteFor(user.role.name) : '/login';
  const canEditClass = hasPermission('class.edit');
  const { id } = useParams<{ id: string }>();
  const { data: c, isLoading } = useClass(id);
  const { data: subjects } = useSubjects({ is_active: true, education_system: c?.education_system });
  const { data: teachersData } = useQuery({
    queryKey: ['teachers', 'list', { all: true }],
    queryFn: () => teachersApi.list({ per_page: 100 }),
  });

  const attach = useAttachSubjectToClass(id);
  const detach = useDetachSubjectFromClass(id);

  const [open, setOpen] = useState(false);
  const [subjectId, setSubjectId] = useState('');
  const [teacherId, setTeacherId] = useState('');
  const [assigningSubjectId, setAssigningSubjectId] = useState<string | null>(null);

  const linkedSubjectIds = useMemo(() => new Set((c?.subjects ?? []).map((s) => s.id)), [c]);
  const availableSubjects = useMemo(
    () => (subjects ?? []).filter((s) => !linkedSubjectIds.has(s.id)
      && (!c || s.education_system === 'both' || s.education_system === c.education_system)),
    [subjects, linkedSubjectIds, c],
  );

  const submit = async () => {
    try {
      await attach.mutateAsync({
        subject_id: subjectId,
        teacher_id: teacherId || undefined,
      });
      toast.success('Subject attached to class.');
      setOpen(false);
      setSubjectId(''); setTeacherId('');
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? 'Could not attach subject.');
    }
  };

  if (isLoading) return <div className="space-y-4"><Skeleton className="h-8 w-1/3" /><Skeleton className="h-32" /></div>;
  if (!c) return (
    <Card><CardContent className="py-12 text-center">
      <h2 className="text-lg font-semibold text-ink">Class not found</h2>
      <Button className="mt-6" variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />}
        onClick={() => router.push(classesBase)}>Back to classes</Button>
    </CardContent></Card>
  );

  // Import-compatible class roster export.
  const exportRoster = () => {
    const rows: string[][] = [
      ['Name', 'Matricule', 'Class', 'Date of birth', 'Place of birth', 'Gender'],
      ...(c.students ?? []).map((student) => [
        student.import_name || student.full_name,
        student.official_matricule || student.admission_number,
        c.name,
        student.date_of_birth ?? '',
        student.place_of_birth ?? '',
        student.gender === 'female' ? 'Female' : 'Male',
      ]),
    ];
    const escapeCell = (value: string) => {
      const text = String(value);
      // Spreadsheet programs may evaluate formula-like CSV cells even when
      // quoted. A leading tab is displayed harmlessly and our importer trims
      // it, so an exported roster remains safe and round-trip compatible.
      const safe = /^[\s]*[=+\-@]/.test(text) ? `\t${text}` : text;
      return `"${safe.replace(/"/g, `""`)}"`;
    };
    const csv = `\uFEFF${rows.map((row) => row.map(escapeCell).join(';')).join('\r\n')}`;
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${c.name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}-students-import-format.csv`;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  };

  return (
    <div>
      <PageHeader
        breadcrumb={isSecretary
          ? [
              { label: 'Home', href: dashboardHref },
              { label: 'Classes', href: classesBase },
              { label: c.name },
            ]
          : [
              { label: 'Home', href: dashboardHref },
              { label: 'Academic structure', href: '/admin/academic' },
              { label: 'Classes', href: classesBase },
              { label: c.name },
            ]}
        title={c.name}
        description={`${c.cycle_label ?? 'Cycle not assigned'} · ${c.grade_level} · ${c.language === 'fr' ? 'French programme' : 'English programme'} · ${c.academic_year?.title ?? ''}`}
        actions={
          <Button variant="outline" leftIcon={<Download className="h-4 w-4" />} onClick={exportRoster}>
            Export student list (import format)
          </Button>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card><CardContent className="py-4">
          <p className="text-sm text-secondary-500">Capacity</p>
          <p className="text-2xl font-semibold text-ink">{c.students_count ?? 0} <span className="text-base text-secondary-500">/ {c.capacity}</span></p>
        </CardContent></Card>
        <Card><CardContent className="py-4">
          <p className="text-sm text-secondary-500">Form master</p>
          <p className="text-base font-semibold text-ink">{c.form_master?.full_name ?? <span className="text-secondary-400">None assigned</span>}</p>
        </CardContent></Card>
        <Card><CardContent className="py-4">
          <p className="text-sm text-secondary-500">Subjects</p>
          <p className="text-2xl font-semibold text-ink">{c.subjects?.length ?? 0}</p>
        </CardContent></Card>
      </div>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Students in this class</CardTitle>
          <CardDescription>{c.students?.length ?? 0} of {c.capacity} available places are currently occupied.</CardDescription>
        </CardHeader>
        <CardContent>
          {(c.students ?? []).length === 0 ? (
            <div className="py-8 text-center text-sm text-secondary-500">No students are registered in this class.</div>
          ) : (
            <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
              {(c.students ?? []).map((student) => (
                <button
                  key={student.id}
                  type="button"
                  onClick={() => router.push(`${studentsBase}/${student.id}`)}
                  className="flex items-center gap-3 rounded-card border border-secondary-200 p-3 text-left transition-colors hover:border-primary-300 hover:bg-primary-50"
                >
                  {student.photo_url ? (
                    <img src={student.photo_url} alt="" className="h-10 w-10 rounded-full object-cover" />
                  ) : (
                    <Avatar name={student.full_name} size="sm" />
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-ink">{student.full_name}</p>
                    <p className="text-xs text-secondary-500">{student.admission_number}</p>
                  </div>
                  <Badge variant={student.status === 'active' ? 'success' : 'secondary'}>{student.status_label}</Badge>
                </button>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Subjects taught in this class</CardTitle>
            <CardDescription>Assign a teacher to each subject. Its coefficient is managed in the subject settings.</CardDescription>
          </div>
          {canEditClass && (
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setOpen(true)}
              disabled={availableSubjects.length === 0}>Add subject</Button>
          )}
        </CardHeader>
        <CardContent>
          {(c.subjects ?? []).length === 0 ? (
            <div className="py-8 text-center text-sm text-secondary-500">No subjects attached yet.</div>
          ) : (
            <ul className="space-y-2">
              {(c.subjects ?? []).map((s) => (
                <li key={s.id} className="flex items-center gap-3 rounded-card border border-secondary-200 p-3">
                  <div className="h-3 w-3 rounded-full" style={{ backgroundColor: s.color }} />
                  <div className="flex-1">
                    <p className="font-medium text-ink">{s.name}</p>
                    <p className="text-xs text-secondary-500">
                      {s.code} · Coefficient {s.pivot.coefficient}
                      {' · '}
                    </p>
                  </div>
                  {canEditClass ? <select
                    aria-label={`Teacher for ${s.name}`}
                    value={s.pivot.teacher_id ?? ''}
                    disabled={assigningSubjectId === s.id && attach.isPending}
                    onClick={(event) => event.stopPropagation()}
                    onChange={async (event) => {
                      const selectedTeacherId = event.target.value || null;
                      setAssigningSubjectId(s.id);
                      try {
                        await attach.mutateAsync({
                          subject_id: s.id,
                          teacher_id: selectedTeacherId,
                          weekly_frequency: s.pivot.weekly_frequency,
                        });
                        toast.success(selectedTeacherId ? 'Teacher assigned to this class subject.' : 'Teacher assignment removed.');
                      } catch (err: any) {
                        toast.error(err?.response?.data?.message ?? 'Could not update the teacher assignment.');
                      } finally {
                        setAssigningSubjectId(null);
                      }
                    }}
                    className="h-9 max-w-64 rounded-input border border-secondary-300 bg-surface px-2 text-sm"
                  >
                    <option value="">No teacher assigned</option>
                    {(teachersData?.data ?? []).map((teacher) => (
                      <option key={teacher.id} value={teacher.id}>{teacher.full_name} · {teacher.employee_number}</option>
                    ))}
                  </select> : (
                    <span className="text-sm text-secondary-600">
                      {(teachersData?.data ?? []).find((teacher) => teacher.id === s.pivot.teacher_id)?.full_name ?? 'No teacher assigned'}
                    </span>
                  )}
                  {canEditClass && <button onClick={async () => {
                    if (!window.confirm(`Remove ${s.name} from this class?`)) return;
                    try { await detach.mutateAsync(s.id); toast.success('Subject removed.'); }
                    catch { toast.error('Could not remove.'); }
                  }} className="rounded-button p-1.5 text-secondary-500 hover:bg-danger-light hover:text-danger" aria-label="Detach">
                    <X className="h-4 w-4" />
                  </button>}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      <Modal open={open} onClose={() => setOpen(false)} title="Attach subject" size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={attach.isPending} disabled={!subjectId}>Attach</Button>
        </>}>
        <div className="space-y-3">
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Subject</label>
            <select value={subjectId} onChange={(e) => setSubjectId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">Select subject</option>
              {availableSubjects.map((s) => <option key={s.id} value={s.id}>{s.name} · {s.code} · Coeff {s.coefficient}</option>)}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Teacher (optional)</label>
            <select value={teacherId} onChange={(e) => setTeacherId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">Assign later</option>
              {(teachersData?.data ?? []).map((t) => <option key={t.id} value={t.id}>{t.full_name} · {t.employee_number}</option>)}
            </select>
          </div>
        </div>
      </Modal>
    </div>
  );
}
