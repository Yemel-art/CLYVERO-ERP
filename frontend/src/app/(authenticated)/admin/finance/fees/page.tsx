'use client';

import { useState, useMemo } from 'react';
import { toast } from 'sonner';
import { Plus, Pencil, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAcademicYears, useClasses } from '@/hooks/academic';
import { useFees, useCreateFee, useUpdateFee, useDeleteFee } from '@/hooks/finance';
import { FEE_CATEGORIES, type FeeStructure, type FeeCategory, type FeeFrequency } from '@/types/finance';
import { useAuthStore } from '@/store/auth';

const FREQUENCIES: { value: FeeFrequency; label: string }[] = [
  { value: 'annual',   label: 'Annual' },
  { value: 'termly',   label: 'Termly' },
  { value: 'monthly',  label: 'Monthly' },
  { value: 'one_time', label: 'One-time' },
];

function formatXAF(n: number) {
  return new Intl.NumberFormat('fr-CM').format(Math.round(n)) + ' XAF';
}

export default function FeesPage() {
  const role = useAuthStore((s) => s.user?.role.name);
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const financeBase = role === 'secretary' ? '/secretary/finance' : '/admin/finance';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const canCreate = hasPermission('fee.create');
  const canEdit = hasPermission('fee.edit');
  const canDelete = hasPermission('fee.delete');
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((y) => y.status === 'active') ?? years?.[0], [years]);
  const [yearId, setYearId] = useState<string | undefined>(undefined);
  const effective = yearId ?? activeYear?.id;
  const { data: classes } = useClasses({ academic_year_id: effective });
  const { data: fees, isLoading } = useFees({ academic_year_id: effective });

  const create = useCreateFee();
  const remove = useDeleteFee();

  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<FeeStructure | null>(null);
  const update = useUpdateFee(editing?.id ?? '');

  const [name, setName] = useState('');
  const [category, setCategory] = useState<FeeCategory>('tuition');
  const [amount, setAmount] = useState(0);
  const [frequency, setFrequency] = useState<FeeFrequency>('annual');
  const [classId, setClassId] = useState('');
  const [isRequired, setIsRequired] = useState(true);

  const openCreate = () => {
    setEditing(null);
    setName(''); setCategory('tuition'); setAmount(0); setFrequency('annual');
    setClassId(''); setIsRequired(true);
    setOpen(true);
  };

  const openEdit = (f: FeeStructure) => {
    setEditing(f);
    setName(f.name); setCategory(f.category); setAmount(f.amount); setFrequency(f.frequency);
    setClassId(f.class_id ?? ''); setIsRequired(f.is_required);
    setOpen(true);
  };

  const submit = async () => {
    if (!effective) { toast.error('Select an academic year first.'); return; }
    const payload = {
      academic_year_id: effective,
      class_id: classId || null,
      name, category, amount, frequency, is_required: isRequired,
    };
    try {
      if (editing) {
        await update.mutateAsync(payload);
        toast.success('Fee updated.');
      } else {
        await create.mutateAsync(payload);
        toast.success('Fee created.');
      }
      setOpen(false);
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? 'Could not save.');
    }
  };

  const onDelete = async (f: FeeStructure) => {
    if (!window.confirm(`Delete fee "${f.name}"? Existing invoice items keep their amounts.`)) return;
    try { await remove.mutateAsync(f.id); toast.success('Fee deleted.'); }
    catch { toast.error('Could not delete.'); }
  };

  // Group fees by category for display.
  const grouped = useMemo(() => {
    const out: Record<FeeCategory, FeeStructure[]> = { tuition: [], cafeteria: [], uniform: [], transport: [], exam: [], other: [] };
    (fees ?? []).forEach((f) => out[f.category].push(f));
    return out;
  }, [fees]);

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Finance', href: financeBase },
          { label: 'Fee structures' },
        ]}
        title="Fee structures"
        description="Define fees per academic year. Required fees are added to every student's invoice on enrollment."
        actions={canCreate ? <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>New fee</Button> : undefined}
      />

      <Card className="mb-6">
        <CardContent className="flex items-center gap-3 py-4">
          <label className="text-sm font-medium text-secondary-700">Academic year</label>
          <select value={effective ?? ''} onChange={(e) => setYearId(e.target.value)}
            className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
            {(years ?? []).map((y) => <option key={y.id} value={y.id}>{y.title}</option>)}
          </select>
        </CardContent>
      </Card>

      {isLoading ? <Skeleton className="h-64" /> : (fees ?? []).length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">No fees defined yet.</CardContent></Card>
      ) : (
        <div className="space-y-4">
          {FEE_CATEGORIES.map(({ value, label }) => {
            const list = grouped[value];
            if (list.length === 0) return null;
            return (
              <Card key={value}>
                <CardContent className="py-4">
                  <p className="mb-3 text-sm font-semibold uppercase tracking-wider text-secondary-500">{label}</p>
                  <ul className="divide-y divide-secondary-100">
                    {list.map((f) => (
                      <li key={f.id} className="flex items-center justify-between py-2">
                        <div>
                          <div className="flex items-center gap-2">
                            <p className="font-medium text-ink">{f.name}</p>
                            <Badge variant="secondary">{f.frequency.replace('_', ' ')}</Badge>
                            {!f.is_required && <Badge variant="info">Optional</Badge>}
                            {f.school_class && <Badge variant="info">{f.school_class.name}</Badge>}
                          </div>
                        </div>
                        <div className="flex items-center gap-3">
                          <p className="font-semibold text-ink">{formatXAF(f.amount)}</p>
                          {canEdit && <button onClick={() => openEdit(f)} className="rounded-button p-1.5 text-secondary-500 hover:bg-secondary-100" aria-label="Edit"><Pencil className="h-4 w-4" /></button>}
                          {canDelete && <button onClick={() => onDelete(f)} className="rounded-button p-1.5 text-secondary-500 hover:bg-danger-light hover:text-danger" aria-label="Delete"><Trash2 className="h-4 w-4" /></button>}
                        </div>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title={editing ? 'Edit fee' : 'New fee'} size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={editing ? update.isPending : create.isPending} disabled={!name || amount <= 0}>
            {editing ? 'Save changes' : 'Create fee'}
          </Button>
        </>}>
        <div className="space-y-3">
          <Input label="Name" value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Tuition – Form 1" />
          <div className="grid grid-cols-2 gap-3">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-secondary-700">Category</label>
              <select value={category} onChange={(e) => setCategory(e.target.value as FeeCategory)}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                {FEE_CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
              </select>
            </div>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-secondary-700">Frequency</label>
              <select value={frequency} onChange={(e) => setFrequency(e.target.value as FeeFrequency)}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                {FREQUENCIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
              </select>
            </div>
          </div>
          <Input type="number" label="Amount (XAF)" min={0} step={500} value={amount} onChange={(e) => setAmount(Number(e.target.value))} />
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Applies to (optional)</label>
            <select value={classId} onChange={(e) => setClassId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">All classes</option>
              {(classes ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <label className="flex items-center gap-2 text-sm text-secondary-700">
            <input type="checkbox" checked={isRequired} onChange={(e) => setIsRequired(e.target.checked)}
              className="rounded border-secondary-300 text-primary-600" />
            Required (auto-added to every applicable student's invoice)
          </label>
        </div>
      </Modal>
    </div>
  );
}
