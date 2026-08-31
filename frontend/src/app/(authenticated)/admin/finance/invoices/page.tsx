'use client';

import { useState, useMemo, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { toast } from 'sonner';
import { Plus, Search } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Pagination } from '@/components/ui/Pagination';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { useInvoices, useGenerateInvoice } from '@/hooks/finance';
import { useAcademicYears } from '@/hooks/academic';
import { studentsApi } from '@/lib/api/students';
import type { Invoice, InvoiceStatus } from '@/types/finance';
import { useAuthStore } from '@/store/auth';

const statusVariant: Record<InvoiceStatus, 'success' | 'warning' | 'danger' | 'info' | 'secondary'> = {
  draft: 'secondary', issued: 'info', partially_paid: 'warning',
  paid: 'success', overdue: 'danger', cancelled: 'secondary',
};

function formatXAF(n: number) {
  return new Intl.NumberFormat('fr-CM').format(Math.round(n)) + ' XAF';
}

function useDebounced<T>(value: T, ms = 300): T {
  const [d, set] = useState(value);
  useEffect(() => { const id = setTimeout(() => set(value), ms); return () => clearTimeout(id); }, [value, ms]);
  return d;
}

export default function InvoicesPage() {
  const router = useRouter();
  const role = useAuthStore((s) => s.user?.role.name);
  const financeBase = role === 'secretary' ? '/secretary/finance' : '/admin/finance';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const debouncedQ = useDebounced(search, 300);
  const { data, isLoading, isFetching } = useInvoices({ q: debouncedQ || undefined, page, per_page: 25 });
  useEffect(() => { setPage(1); }, [debouncedQ]);

  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((y) => y.status === 'active'), [years]);

  const generate = useGenerateInvoice();
  const [open, setOpen] = useState(false);
  const [studentQuery, setStudentQuery] = useState('');
  const debouncedStudent = useDebounced(studentQuery, 300);
  const { data: studentResults } = useQuery({
    queryKey: ['students', 'search', debouncedStudent],
    queryFn: () => studentsApi.list({ q: debouncedStudent, per_page: 8 }),
    enabled: debouncedStudent.trim().length >= 1,
  });
  const [studentId, setStudentId] = useState<string | null>(null);
  const [studentName, setStudentName] = useState('');
  const [dueAt, setDueAt] = useState('');

  const submit = async () => {
    if (!studentId || !activeYear) return;
    try {
      const inv = await generate.mutateAsync({
        student_id: studentId,
        academic_year_id: activeYear.id,
        due_at: dueAt || undefined,
      });
      toast.success(`Invoice ${inv.invoice_number} generated.`);
      setOpen(false);
      setStudentId(null); setStudentName(''); setDueAt(''); setStudentQuery('');
      router.push(`${financeBase}/invoices/${inv.id}`);
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? 'Could not generate invoice.');
    }
  };

  const columns: Column<Invoice>[] = [
    { key: 'num', header: 'Invoice', cell: (i) => (
      <Link
        href={`${financeBase}/invoices/${i.id}`}
        className="font-mono text-xs font-semibold text-primary-600 hover:underline"
        onClick={(event) => event.stopPropagation()}
      >
        {i.invoice_number}
      </Link>
    ) },
    { key: 'student', header: 'Student', cell: (i) => i.student ? (
      <div><p className="font-medium text-ink">{i.student.full_name}</p><p className="text-xs text-secondary-500">{i.student.admission_number}</p></div>
    ) : '—' },
    { key: 'issued', header: 'Issued', cell: (i) => i.issued_at },
    { key: 'due', header: 'Due', cell: (i) => i.due_at },
    { key: 'total', header: 'Total', align: 'right', cell: (i) => formatXAF(i.total) },
    { key: 'paid', header: 'Paid', align: 'right', cell: (i) => formatXAF(i.paid) },
    { key: 'balance', header: 'Balance', align: 'right',
      cell: (i) => <span className={i.balance > 0 ? 'text-danger font-medium' : 'text-success'}>{formatXAF(i.balance)}</span> },
    { key: 'status', header: 'Status', cell: (i) => <Badge variant={statusVariant[i.status]}>{i.status.replace('_', ' ')}</Badge> },
  ];

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Finance', href: financeBase },
          { label: 'Invoices' },
        ]}
        title="Invoices"
        description="Generate, view, and record payments on student invoices."
        actions={<Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setOpen(true)} disabled={!activeYear}>Generate invoice</Button>}
      />

      <div className="mb-4 max-w-md">
        <Input placeholder="Search by invoice number or student…" value={search}
          onChange={(e) => setSearch(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(i) => i.id}
        isLoading={isLoading || (isFetching && !data)}
        emptyTitle="No invoices yet"
        emptyDescription="Generate an invoice for the first student to get started."
        onRowClick={(i) => router.push(`${financeBase}/invoices/${i.id}`)}
      />

      {data && data.meta.total > 0 && (
        <Pagination page={data.meta.page} lastPage={data.meta.last_page}
          total={data.meta.total} perPage={data.meta.per_page} onPageChange={setPage} />
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="Generate invoice" size="md"
        description={activeYear ? `Will be issued for ${activeYear.title}.` : ''}
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={generate.isPending} disabled={!studentId}>Generate</Button>
        </>}>
        <div className="space-y-3">
          {studentId ? (
            <Card><CardContent className="flex items-center justify-between py-2">
              <p className="text-sm"><span className="font-medium">{studentName}</span></p>
              <Button size="sm" variant="ghost" onClick={() => { setStudentId(null); setStudentName(''); }}>Change</Button>
            </CardContent></Card>
          ) : (
            <>
              <Input label="Find student" placeholder="Search by name, admission number, or class…"
                value={studentQuery} onChange={(e) => setStudentQuery(e.target.value)} />
              {studentResults && studentResults.data.length > 0 && (
                <ul className="max-h-48 space-y-1 overflow-y-auto rounded-card border border-secondary-200 p-1">
                  {studentResults.data.map((s) => (
                    <li key={s.id}>
                      <button onClick={() => { setStudentId(s.id); setStudentName(s.full_name); }}
                        className="flex w-full items-center justify-between rounded-button p-2 text-left text-sm hover:bg-secondary-50">
                        <span><span className="font-medium">{s.full_name}</span> <span className="text-xs text-secondary-500">{s.admission_number}{s.class?.name ? ` · ${s.class.name}` : ''}</span></span>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </>
          )}
          <Input type="date" label="Due date (optional)" value={dueAt} onChange={(e) => setDueAt(e.target.value)} />
        </div>
      </Modal>
    </div>
  );
}
