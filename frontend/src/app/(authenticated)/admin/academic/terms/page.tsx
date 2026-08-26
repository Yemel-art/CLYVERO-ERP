'use client';

import { useState, useMemo } from 'react';
import { toast } from 'sonner';
import { Plus, Power, X } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAcademicYears, useTerms, useCreateTerm, useActivateTerm, useCloseTerm } from '@/hooks/academic';

const statusVariant = { upcoming: 'info', active: 'success', closed: 'secondary' } as const;

export default function TermsPage() {
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((y) => y.status === 'active') ?? years?.[0], [years]);
  const [yearId, setYearId] = useState<string | undefined>(undefined);

  // Sync selector default once years load.
  const effectiveYearId = yearId ?? activeYear?.id;
  const { data: terms, isLoading } = useTerms(effectiveYearId);
  const create = useCreateTerm(effectiveYearId ?? '');
  const activate = useActivateTerm();
  const close = useCloseTerm();

  const [open, setOpen] = useState(false);
  const [name, setName] = useState('');
  const [sequence, setSequence] = useState(1);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  const submit = async () => {
    try {
      await create.mutateAsync({ name, sequence, start_date: startDate, end_date: endDate });
      toast.success(`${name} created.`);
      setOpen(false);
      setName(''); setSequence(1); setStartDate(''); setEndDate('');
    } catch {
      toast.error('Could not create the term.');
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: '/admin/dashboard' },
          { label: 'Academic structure', href: '/admin/academic' },
          { label: 'Terms' },
        ]}
        title="Terms"
        description="Set up the terms within each academic year. Only one term can be active at a time per year."
        actions={effectiveYearId && (
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setOpen(true)}>New term</Button>
        )}
      />

      <div className="mb-6 max-w-sm">
        <label className="mb-1.5 block text-sm font-medium text-secondary-700">Academic year</label>
        <select value={effectiveYearId ?? ''} onChange={(e) => setYearId(e.target.value)}
          className="h-10 w-full rounded-input border border-secondary-300 bg-surface px-3 text-sm">
          <option value="" disabled>Select a year</option>
          {(years ?? []).map((y) => <option key={y.id} value={y.id}>{y.title} ({y.status})</option>)}
        </select>
      </div>

      {!effectiveYearId ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">Create an academic year first.</CardContent></Card>
      ) : isLoading ? <Skeleton className="h-48" /> : (
        <div className="space-y-3">
          {(terms ?? []).length === 0 ? (
            <Card><CardContent className="py-12 text-center text-sm text-secondary-500">No terms yet for this academic year.</CardContent></Card>
          ) : (
            (terms ?? []).map((t) => (
              <Card key={t.id}>
                <CardContent className="flex items-center justify-between py-4">
                  <div>
                    <div className="flex items-center gap-2">
                      <p className="font-semibold text-ink">{t.name}</p>
                      <Badge variant={statusVariant[t.status]}>{t.status}</Badge>
                      <span className="text-xs text-secondary-500">Sequence {t.sequence}</span>
                    </div>
                    <p className="text-sm text-secondary-500">{t.start_date} → {t.end_date}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    {t.status !== 'active' && t.status !== 'closed' && (
                      <Button size="sm" variant="outline" leftIcon={<Power className="h-4 w-4" />}
                        onClick={async () => {
                          try { await activate.mutateAsync(t.id); toast.success(`${t.name} activated.`); } catch { toast.error('Could not activate term.'); }
                        }}>Activate</Button>
                    )}
                    {t.status === 'active' && (
                      <Button size="sm" variant="danger" leftIcon={<X className="h-4 w-4" />}
                        onClick={async () => {
                          try { await close.mutateAsync(t.id); toast.success(`${t.name} closed.`); } catch { toast.error('Could not close term.'); }
                        }}>Close</Button>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="Add term" size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={create.isPending} disabled={!name || !startDate || !endDate}>Create term</Button>
        </>}>
        <div className="space-y-3">
          <Input label="Term name" placeholder="e.g. First Term" value={name} onChange={(e) => setName(e.target.value)} />
          <div className="grid grid-cols-3 gap-3">
            <Input type="number" min={1} max={5} label="Sequence" value={sequence} onChange={(e) => setSequence(Number(e.target.value))} />
            <Input type="date" label="Start date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            <Input type="date" label="End date" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
          </div>
        </div>
      </Modal>
    </div>
  );
}
