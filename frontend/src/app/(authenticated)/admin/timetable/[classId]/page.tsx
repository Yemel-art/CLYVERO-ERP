'use client';

import { useState, useMemo } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { ArrowLeft, Plus, Printer, Trash2 } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Skeleton } from '@/components/ui/Skeleton';
import { useClass } from '@/hooks/academic';
import { useClassTimetable, useCreateSlot, useUpdateSlot, useDeleteSlot } from '@/hooks/timetable';
import { teachersApi } from '@/lib/api/teachers';
import { DAYS, type DayOfWeek, type TimetableSlot } from '@/types/timetable';

export default function ClassTimetablePage() {
  const router = useRouter();
  const { classId } = useParams<{ classId: string }>();
  const { data: c } = useClass(classId);
  const { data: slots, isLoading } = useClassTimetable(classId);
  const { data: teachersData } = useQuery({
    queryKey: ['teachers', 'list', { all: true }],
    queryFn: () => teachersApi.list({ per_page: 100 }),
  });

  const create = useCreateSlot();
  const update = useUpdateSlot();
  const remove = useDeleteSlot();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<TimetableSlot | null>(null);
  const [day, setDay] = useState<DayOfWeek>('monday');
  const [start, setStart] = useState('08:00');
  const [end, setEnd] = useState('09:00');
  const [subjectId, setSubjectId] = useState('');
  const [teacherId, setTeacherId] = useState('');
  const [room, setRoom] = useState('');

  const openCreate = (d?: DayOfWeek) => {
    setEditing(null);
    if (d) setDay(d);
    setStart('08:00'); setEnd('09:00'); setSubjectId(''); setTeacherId(''); setRoom('');
    setOpen(true);
  };

  const openEdit = (s: TimetableSlot) => {
    setEditing(s);
    setDay(s.day_of_week);
    setStart(s.start_time); setEnd(s.end_time);
    setSubjectId(s.subject_id ?? ''); setTeacherId(s.teacher_id ?? ''); setRoom(s.room ?? '');
    setOpen(true);
  };

  const submit = async () => {
    if (!classId) return;
    const payload: Partial<TimetableSlot> = {
      class_id: classId,
      day_of_week: day,
      start_time: start, end_time: end,
      subject_id: subjectId || null,
      teacher_id: teacherId || null,
      room: room || null,
    };
    try {
      if (editing) {
        await update.mutateAsync({ id: editing.id, payload });
        toast.success('Slot updated.');
      } else {
        await create.mutateAsync(payload);
        toast.success('Slot added.');
      }
      setOpen(false);
    } catch (err: any) {
      const errors = err?.response?.data?.errors as Record<string, string[]> | undefined;
      const firstError = errors ? Object.values(errors).flat()[0] : undefined;
      toast.error(firstError ?? err?.response?.data?.message ?? err?.message ?? 'Could not save slot.');
    }
  };

  const onDelete = async (s: TimetableSlot) => {
    if (!window.confirm('Remove this slot?')) return;
    try { await remove.mutateAsync(s.id); toast.success('Slot removed.'); }
    catch { toast.error('Could not remove.'); }
  };

  // Group slots by day for the grid.
  const byDay = useMemo(() => {
    const map: Record<DayOfWeek, TimetableSlot[]> = {
      monday: [], tuesday: [], wednesday: [], thursday: [], friday: [], saturday: [],
    };
    (slots ?? []).forEach((s) => map[s.day_of_week].push(s));
    return map;
  }, [slots]);

  if (isLoading || !c) return <div className="space-y-4"><Skeleton className="h-8" /><Skeleton className="h-96" /></div>;

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: '/admin/dashboard' },
          { label: 'Timetable', href: '/admin/timetable' },
          { label: c.name },
        ]}
        title={`Timetable · ${c.name}`}
        description={c.academic_year?.title ?? ''}
        actions={<>
          <Button variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />}
            onClick={() => router.push('/admin/timetable')}>Back</Button>
          <Button variant="outline" leftIcon={<Printer className="h-4 w-4" />}
            onClick={() => window.print()}>Print / PDF</Button>
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => openCreate()}>Add slot</Button>
        </>}
      />

      <Card>
        <CardContent className="py-4">
          <div className="grid grid-cols-6 gap-3">
            {DAYS.map((d) => (
              <div key={d.value} className="min-h-[200px]">
                <div className="mb-2 flex items-center justify-between">
                  <p className="text-sm font-semibold text-secondary-700">{d.label}</p>
                  <button onClick={() => openCreate(d.value)} aria-label={`Add ${d.label}`}
                    className="rounded-button p-0.5 text-secondary-400 hover:bg-secondary-100 hover:text-primary-600">
                    <Plus className="h-3.5 w-3.5" />
                  </button>
                </div>
                <div className="space-y-2">
                  {byDay[d.value].length === 0 && (
                    <p className="rounded-card border border-dashed border-secondary-200 py-4 text-center text-xs text-secondary-400">Empty</p>
                  )}
                  {byDay[d.value].map((s) => (
                    <div key={s.id} className="group cursor-pointer rounded-card border border-secondary-200 bg-surface p-2 text-xs transition-colors hover:border-primary-300"
                      onClick={() => openEdit(s)}>
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <p className="font-semibold text-ink">{s.subject?.name ?? 'No subject'}</p>
                          <p className="text-secondary-500">{s.start_time}–{s.end_time}</p>
                          {s.teacher && <p className="mt-0.5 truncate text-secondary-500">{s.teacher.full_name}</p>}
                          {s.room && <p className="text-secondary-400">Room {s.room}</p>}
                        </div>
                        <button onClick={(e) => { e.stopPropagation(); onDelete(s); }}
                          className="invisible rounded-button p-0.5 text-danger group-hover:visible hover:bg-danger-light"
                          aria-label="Delete slot">
                          <Trash2 className="h-3 w-3" />
                        </button>
                      </div>
                      {s.subject && <div className="mt-1 h-0.5 w-full rounded-full" style={{ backgroundColor: s.subject.color }} />}
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      <Modal open={open} onClose={() => setOpen(false)} title={editing ? 'Edit slot' : 'New slot'} size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={create.isPending || update.isPending} disabled={!start || !end}>
            {editing ? 'Save changes' : 'Add slot'}
          </Button>
        </>}>
        <div className="space-y-3">
          <div className="grid grid-cols-3 gap-3">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-secondary-700">Day</label>
              <select value={day} onChange={(e) => setDay(e.target.value as DayOfWeek)}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                {DAYS.map((d) => <option key={d.value} value={d.value}>{d.label}</option>)}
              </select>
            </div>
            <Input type="time" label="Start" value={start} onChange={(e) => setStart(e.target.value)} />
            <Input type="time" label="End" value={end} onChange={(e) => setEnd(e.target.value)} />
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Subject</label>
            <select value={subjectId} onChange={(e) => setSubjectId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">No subject</option>
              {(c.subjects ?? []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Teacher</label>
            <select value={teacherId} onChange={(e) => setTeacherId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">No teacher</option>
              {(teachersData?.data ?? []).map((t) => <option key={t.id} value={t.id}>{t.full_name}</option>)}
            </select>
          </div>
          <Input label="Room (optional)" value={room} onChange={(e) => setRoom(e.target.value)} />
        </div>
      </Modal>
    </div>
  );
}
