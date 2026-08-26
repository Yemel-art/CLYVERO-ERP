'use client';

import { useState, useEffect, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Plus, Search, MoreVertical, Eye, Pencil, Archive as ArchiveIcon, RotateCcw } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Pagination } from '@/components/ui/Pagination';
import { Avatar } from '@/components/ui/Avatar';
import { TeacherStatusBadge } from '@/components/teachers/TeacherStatusBadge';
import { ArchiveTeacherDialog, RestoreTeacherDialog } from '@/components/teachers/TeacherArchiveDialogs';
import { useTeachers, useTeacherStatistics } from '@/hooks/teachers';
import { useAuthStore } from '@/store/auth';
import type { Teacher, TeacherFilters } from '@/types/teacher';

function useDebounced<T>(value: T, ms = 300): T {
  const [d, set] = useState(value);
  useEffect(() => { const id = setTimeout(() => set(value), ms); return () => clearTimeout(id); }, [value, ms]);
  return d;
}

export default function TeachersListPage() {
  const router = useRouter();
  const role = useAuthStore((s) => s.user?.role.name);
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const teachersBase = role === 'secretary' ? '/secretary/teachers' : '/admin/teachers';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const canCreate = hasPermission('teacher.create');

  const [search, setSearch] = useState('');
  const [showArchived, setShowArchived] = useState(false);
  const [page, setPage] = useState(1);
  const debouncedQ = useDebounced(search, 300);

  const [archiveTarget, setArchiveTarget] = useState<Teacher | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<Teacher | null>(null);
  const [openFor, setOpenFor] = useState<string | null>(null);

  const filters: TeacherFilters = useMemo(() => ({
    q: debouncedQ || undefined,
    include_archived: showArchived || undefined,
    page,
    per_page: 20,
  }), [debouncedQ, page, showArchived]);
  useEffect(() => { setPage(1); }, [debouncedQ, showArchived]);
  const { data, isLoading, isFetching, isError, error } = useTeachers(filters);
  const { data: stats } = useTeacherStatistics();

  useEffect(() => {
    if (!openFor) return;
    const h = () => setOpenFor(null);
    document.addEventListener('click', h);
    return () => document.removeEventListener('click', h);
  }, [openFor]);

  const columns: Column<Teacher>[] = [
    {
      key: 'name', header: 'Teacher',
      cell: (t) => (
        <div className="flex items-center gap-3">
          {t.photo_url ? <img src={t.photo_url} alt="" className="h-9 w-9 rounded-full object-cover" /> : <Avatar name={t.full_name} size="sm" />}
          <div><p className="font-medium text-ink">{t.full_name}</p><p className="text-xs text-secondary-500">{t.employee_number}</p></div>
        </div>
      ),
    },
    { key: 'qual', header: 'Qualification', cell: (t) => t.qualification ?? '—' },
    { key: 'exp', header: 'Experience', cell: (t) => `${t.years_of_experience} yrs`, align: 'right' },
    { key: 'hired', header: 'Hired', cell: (t) => t.hire_date },
    { key: 'status', header: 'Status', cell: (t) => <TeacherStatusBadge status={t.status} /> },
    {
      key: 'actions', header: <span className="sr-only">Actions</span>, align: 'right',
      cell: (t) => (
        <div className="relative inline-block">
          <button type="button" aria-label="Row actions"
            onClick={(e) => { e.stopPropagation(); setOpenFor((v) => v === t.id ? null : t.id); }}
            className="rounded-button p-1.5 text-secondary-500 hover:bg-secondary-100">
            <MoreVertical className="h-4 w-4" />
          </button>
          {openFor === t.id && (
            <div role="menu" onClick={(e) => e.stopPropagation()}
              className="absolute right-0 z-10 mt-1 w-44 rounded-card border border-secondary-200 bg-surface py-1 text-sm shadow-card">
              <Link href={`${teachersBase}/${t.id}`} className="flex items-center gap-2 px-3 py-2 text-secondary-700 hover:bg-secondary-50"><Eye className="h-4 w-4 text-secondary-400" /> View profile</Link>
              {hasPermission('teacher.edit') && (
                <Link href={`${teachersBase}/${t.id}/edit`} className="flex items-center gap-2 px-3 py-2 text-secondary-700 hover:bg-secondary-50"><Pencil className="h-4 w-4 text-secondary-400" /> Edit</Link>
              )}
              {t.status !== 'archived' && hasPermission('teacher.delete') && (
                <button onClick={() => { setOpenFor(null); setArchiveTarget(t); }}
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-danger hover:bg-danger-light"><ArchiveIcon className="h-4 w-4" /> Archive</button>
              )}
              {t.status === 'archived' && hasPermission('teacher.delete') && (
                <button onClick={() => { setOpenFor(null); setRestoreTarget(t); }}
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-primary-700 hover:bg-primary-50"><RotateCcw className="h-4 w-4" /> Restore</button>
              )}
            </div>
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: dashboardBase }, { label: 'Teachers' }]}
        title="Teachers"
        description="Manage teaching staff records and login accounts."
        actions={canCreate && (
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => router.push(`${teachersBase}/create`)}>Add teacher</Button>
        )}
      />

      <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        {[
          { label: 'Active',     value: stats?.total      ?? '—' },
          { label: 'On leave',   value: stats?.on_leave   ?? '—' },
          { label: 'Archived',   value: stats?.archived   ?? '—' },
          { label: 'Terminated', value: stats?.terminated ?? '—' },
        ].map((s) => (
          <Card key={s.label}><CardContent className="py-4"><p className="text-sm text-secondary-500">{s.label}</p><p className="text-2xl font-semibold text-ink">{s.value}</p></CardContent></Card>
        ))}
      </div>

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="min-w-64 flex-1">
          <Input placeholder="Search by name, employee number, or email…" value={search}
            onChange={(e) => setSearch(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <label className="flex h-10 items-center gap-2 rounded-button border border-secondary-300 px-3 text-sm">
          <input type="checkbox" checked={showArchived} onChange={(event) => setShowArchived(event.target.checked)} />
          Show archived
        </label>
      </div>

      {isError ? (
        <Card>
          <CardContent className="py-10 text-center">
            <p className="font-semibold text-danger">The teacher list could not be loaded.</p>
            <p className="mt-2 text-sm text-secondary-600">
              {(error as { response?: { data?: { message?: string } } })?.response?.data?.message
                ?? (error instanceof Error ? error.message : 'Please reload the page.')}
            </p>
            <Button className="mt-5" variant="outline" onClick={() => window.location.reload()}>Reload teacher list</Button>
          </CardContent>
        </Card>
      ) : (
        <DataTable
          columns={columns}
          rows={data?.data ?? []}
          rowKey={(t) => t.id}
          isLoading={isLoading || (isFetching && !data)}
          emptyTitle="No teachers found"
          emptyDescription={search ? 'Try adjusting your search.' : 'Register your first teacher to get started.'}
          emptyAction={canCreate && !search && (
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => router.push(`${teachersBase}/create`)}>Register first teacher</Button>
          )}
          onRowClick={(t) => router.push(`${teachersBase}/${t.id}`)}
        />
      )}

      {data && data.meta.total > 0 && (
        <Pagination page={data.meta.page} lastPage={data.meta.last_page}
          total={data.meta.total} perPage={data.meta.per_page} onPageChange={setPage} />
      )}

      {archiveTarget && <ArchiveTeacherDialog teacher={archiveTarget} open onClose={() => setArchiveTarget(null)} />}
      {restoreTarget && <RestoreTeacherDialog teacher={restoreTarget} open onClose={() => setRestoreTarget(null)} />}
    </div>
  );
}
