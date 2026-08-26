'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Plus, Pencil, Archive as ArchiveIcon, Search, RotateCcw } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import { useSubjects, useCreateSubject, useUpdateSubject, useArchiveSubject, useRestoreSubject } from '@/hooks/academic';
import type { Subject } from '@/types/academic';

const DEFAULT_COLORS = [
  '#2563eb', '#7c3aed', '#dc2626', '#16a34a', '#ea580c',
  '#0891b2', '#db2777', '#ca8a04', '#4f46e5', '#9333ea',
  '#0f766e', '#65a30d', '#f59e0b', '#be123c', '#475569',
];

export default function SubjectsPage() {
  const [search, setSearch] = useState('');
  const [showArchived, setShowArchived] = useState(false);
  const { data: subjects, isLoading } = useSubjects({ q: search || undefined, include_archived: showArchived || undefined });
  const create = useCreateSubject();
  const archive = useArchiveSubject();
  const restore = useRestoreSubject();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Subject | null>(null);
  const update = useUpdateSubject(editing?.id ?? '');

  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [coefficient, setCoefficient] = useState(1);
  const [educationSystem, setEducationSystem] = useState<'both' | 'secondary_general' | 'secondary_technical'>('both');
  const [color, setColor] = useState(DEFAULT_COLORS[0]);
  const [description, setDescription] = useState('');

  const openCreate = () => {
    setEditing(null);
    const usedColors = new Set((subjects ?? []).filter((subject) => subject.is_active).map((subject) => subject.color.toLowerCase()));
    const firstAvailableColor = DEFAULT_COLORS.find((candidate) => !usedColors.has(candidate.toLowerCase())) ?? DEFAULT_COLORS[0];
    setName(''); setCode(''); setCoefficient(1); setColor(firstAvailableColor); setDescription('');
    setOpen(true);
  };

  const openEdit = (s: Subject) => {
    setEditing(s);
    setName(s.name); setCode(s.code); setCoefficient(s.coefficient); setEducationSystem(s.education_system ?? 'both'); setColor(s.color); setDescription(s.description ?? '');
    setOpen(true);
  };

  const submit = async () => {
    const payload = { name, code, education_system: educationSystem, coefficient, color, description: description || null };
    try {
      if (editing) {
        await update.mutateAsync(payload);
        toast.success('Subject updated.');
      } else {
        await create.mutateAsync(payload);
        toast.success('Subject created.');
      }
      setOpen(false);
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? 'Could not save subject.');
    }
  };

  const onArchive = async (s: Subject) => {
    if (!window.confirm(`Archive subject "${s.name}"?`)) return;
    try {
      await archive.mutateAsync(s.id);
      toast.success(`${s.name} archived.`);
    } catch {
      toast.error('Could not archive subject.');
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: '/admin/dashboard' },
          { label: 'Academic structure', href: '/admin/academic' },
          { label: 'Subjects' },
        ]}
        title="Subjects"
        description="Define the courses offered by the school."
        actions={<Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>New subject</Button>}
      />

      <div className="mb-4 flex max-w-2xl flex-wrap items-center gap-3">
        <div className="min-w-64 flex-1">
          <Input placeholder="Search subjects…" value={search} onChange={(e) => setSearch(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <label className="flex h-10 items-center gap-2 rounded-button border border-secondary-300 px-3 text-sm">
          <input type="checkbox" checked={showArchived} onChange={(event) => setShowArchived(event.target.checked)} />
          Show archived
        </label>
      </div>

      {isLoading ? (
        <Skeleton className="h-48" />
      ) : (subjects ?? []).length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">No subjects yet.</CardContent></Card>
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(subjects ?? []).map((s) => (
            <Card key={s.id}>
              <CardContent className="py-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-start gap-3">
                    <div className="mt-1 h-3 w-3 rounded-full" style={{ backgroundColor: s.color }} />
                    <div>
                      <p className="font-semibold text-ink">{s.name}</p>
                      <p className="text-xs text-secondary-500">{s.code} · Coefficient {s.coefficient}{!s.is_active && ' · Archived'}</p>
                      {s.description && <p className="mt-1 text-sm text-secondary-600 line-clamp-2">{s.description}</p>}
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    <button onClick={() => openEdit(s)} className="rounded-button p-1.5 text-secondary-500 hover:bg-secondary-100" aria-label="Edit">
                      <Pencil className="h-4 w-4" />
                    </button>
                    {s.is_active && (
                      <button onClick={() => onArchive(s)} className="rounded-button p-1.5 text-secondary-500 hover:bg-danger-light hover:text-danger" aria-label="Archive">
                        <ArchiveIcon className="h-4 w-4" />
                      </button>
                    )}
                    {!s.is_active && (
                      <button
                        onClick={() => restore.mutateAsync(s.id).then(() => toast.success(`${s.name} restored.`)).catch(() => toast.error('Could not restore subject.'))}
                        className="rounded-button p-1.5 text-primary-700 hover:bg-primary-50"
                        aria-label="Restore"
                      >
                        <RotateCcw className="h-4 w-4" />
                      </button>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title={editing ? 'Edit subject' : 'New subject'} size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={editing ? update.isPending : create.isPending} disabled={!name || !code}>
            {editing ? 'Save changes' : 'Create subject'}
          </Button>
        </>}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Input label="Name" placeholder="Mathematics" value={name} onChange={(e) => setName(e.target.value)} />
            <Input label="Code" placeholder="MATH" value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} />
          </div>
          <Input type="number" label="Grade coefficient" min={0.1} max={10} step={0.1}
            value={coefficient} onChange={(event) => setCoefficient(Number(event.target.value))} />
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Education system</label>
            <select value={educationSystem} onChange={(event) => setEducationSystem(event.target.value as typeof educationSystem)} className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="both">Both secondary systems</option>
              <option value="secondary_general">Secondary General</option>
              <option value="secondary_technical">Secondary Technical</option>
            </select>
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-secondary-700">Color</label>
            <div className="flex flex-wrap gap-2">
              {DEFAULT_COLORS.map((c) => (
                <button key={c} onClick={() => setColor(c)} type="button"
                  disabled={(subjects ?? []).some((subject) => subject.is_active && subject.id !== editing?.id && subject.color.toLowerCase() === c.toLowerCase())}
                  title={(subjects ?? []).some((subject) => subject.is_active && subject.id !== editing?.id && subject.color.toLowerCase() === c.toLowerCase()) ? 'Already used by another subject' : `Select ${c}`}
                  className={`h-8 w-8 rounded-full ring-2 ring-offset-2 disabled:cursor-not-allowed disabled:opacity-25 ${color === c ? 'ring-primary-600' : 'ring-transparent'}`}
                  style={{ backgroundColor: c }} aria-label={`Color ${c}`} />
              ))}
            </div>
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-secondary-700">Description</label>
            <textarea rows={2} value={description} onChange={(e) => setDescription(e.target.value)}
              className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" />
          </div>
        </div>
      </Modal>
    </div>
  );
}
