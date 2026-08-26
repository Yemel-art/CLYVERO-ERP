'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Plus, Power, Check } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAcademicYears, useCreateYear, useActivateYear } from '@/hooks/academic';
import type { AxiosError } from 'axios';
import type { ApiError } from '@/types/api';

const statusVariant = { upcoming: 'info', active: 'success', archived: 'secondary' } as const;

export default function AcademicYearsPage() {
  const { data: years, isLoading } = useAcademicYears();
  const create = useCreateYear();
  const activate = useActivateYear();
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  const openNextYear = () => {
    const latestStart = Math.max(2026, ...(years ?? []).map((year) => Number(year.title.slice(0, 4))).filter(Number.isFinite));
    const start = latestStart + 1;
    setTitle(`${start}/${start + 1}`);
    setStartDate(`${start}-09-01`);
    setEndDate(`${start + 1}-06-30`);
    setOpen(true);
  };

  const submit = async () => {
    try {
      await create.mutateAsync({ title, start_date: startDate, end_date: endDate });
      toast.success(`Academic year ${title} created.`);
      setOpen(false);
      setTitle(''); setStartDate(''); setEndDate('');
    } catch (error) {
      const response = (error as AxiosError<ApiError>).response?.data;
      const detail = response?.errors ? Object.values(response.errors).flat()[0] : response?.message;
      toast.error(detail ?? 'Could not create academic year.');
    }
  };

  const onActivate = async (id: string, t: string) => {
    if (!window.confirm(`Activate ${t}? This will finalize academic decisions, archive the current year, and create next-year enrollments. This operation requires complete published grades and a promotion policy.`)) return;
    try {
      await activate.mutateAsync(id);
      toast.success(`${t} is now the active academic year.`);
    } catch (error) {
      const response = (error as AxiosError<ApiError>).response?.data;
      const detail = response?.errors ? Object.values(response.errors).flat()[0] : response?.message;
      toast.error(detail ?? 'Could not activate the year.');
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: '/admin/dashboard' },
          { label: 'Academic structure', href: '/admin/academic' },
          { label: 'Academic years' },
        ]}
        title="Academic years"
        description="Only one year can be active at a time."
        actions={<Button leftIcon={<Plus className="h-4 w-4" />} onClick={openNextYear}>New academic year</Button>}
      />

      {isLoading ? (
        <Skeleton className="h-64" />
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {(years ?? []).map((y) => (
            <Card key={y.id}>
              <CardContent className="py-5">
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-lg font-semibold text-ink">{y.title}</p>
                    <p className="text-sm text-secondary-500">{y.start_date} → {y.end_date}</p>
                  </div>
                  <Badge variant={statusVariant[y.status]}>{y.status}</Badge>
                </div>
                {y.status === 'upcoming' && (
                  <Button className="mt-4" variant="outline" size="sm"
                    leftIcon={<Power className="h-4 w-4" />}
                    onClick={() => onActivate(y.id, y.title)}
                    isLoading={activate.isPending && activate.variables === y.id}>
                    Activate
                  </Button>
                )}
                {y.status === 'active' && (
                  <div className="mt-4 flex items-center gap-2 text-sm text-success">
                    <Check className="h-4 w-4" /> Currently active
                  </div>
                )}
                {y.status === 'archived' && (
                  <p className="mt-4 text-sm text-secondary-500">Historical year · read-only records preserved</p>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="Create academic year" size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={create.isPending} disabled={!title || !startDate || !endDate}>Create year</Button>
        </>}>
        <div className="space-y-4">
          <Input label="Title" placeholder="e.g. 2026–2027" value={title} onChange={(e) => setTitle(e.target.value)} />
          <div className="grid grid-cols-2 gap-3">
            <Input type="date" label="Start date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            <Input type="date" label="End date" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
          </div>
        </div>
      </Modal>
    </div>
  );
}
