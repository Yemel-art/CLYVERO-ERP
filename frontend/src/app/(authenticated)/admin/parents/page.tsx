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
import { Badge } from '@/components/ui/Badge';
import { ArchiveParentDialog, RestoreParentDialog } from '@/components/parents/ParentArchiveDialogs';
import { useParents, useParentStatistics } from '@/hooks/parents';
import { useAuthStore } from '@/store/auth';
import type { ParentGuardian, ParentFilters } from '@/types/parent';

function useDebounced<T>(value: T, ms = 300): T {
  const [d, set] = useState(value);
  useEffect(() => { const id = setTimeout(() => set(value), ms); return () => clearTimeout(id); }, [value, ms]);
  return d;
}

export default function ParentsListPage() {
  const router = useRouter();
  const role = useAuthStore((s) => s.user?.role.name);
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const parentsBase = role === 'secretary' ? '/secretary/parents' : '/admin/parents';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const canCreate = hasPermission('parent.create');

  const [search, setSearch] = useState('');
  const [showArchived, setShowArchived] = useState(false);
  const [page, setPage] = useState(1);
  const debouncedQ = useDebounced(search, 300);
  const [archiveTarget, setArchiveTarget] = useState<ParentGuardian | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<ParentGuardian | null>(null);
  const [openFor, setOpenFor] = useState<string | null>(null);

  useEffect(() => { setPage(1); }, [debouncedQ, showArchived]);
  const filters: ParentFilters = useMemo(() => ({
    q: debouncedQ || undefined,
    include_archived: showArchived || undefined,
    page,
    per_page: 20,
  }), [debouncedQ, page, showArchived]);
  const { data, isLoading, isFetching } = useParents(filters);
  const { data: stats } = useParentStatistics();

  useEffect(() => {
    if (!openFor) return;
    const h = () => setOpenFor(null);
    document.addEventListener('click', h);
    return () => document.removeEventListener('click', h);
  }, [openFor]);

  const columns: Column<ParentGuardian>[] = [
    {
      key: 'name', header: 'Parent / Guardian',
      cell: (p) => (
        <div className="flex items-center gap-3">
          <Avatar name={p.full_name} size="sm" />
          <div><p className="font-medium text-ink">{p.full_name}</p><p className="text-xs text-secondary-500">{p.email}</p></div>
        </div>
      ),
    },
    { key: 'phone', header: 'Phone', cell: (p) => p.phone },
    {
      key: 'children', header: 'Children',
      cell: (p) => p.children_count !== undefined && p.children_count > 0
        ? <Badge variant="info">{p.children_count}</Badge>
        : <span className="text-xs text-secondary-500">None</span>,
    },
    {
      key: 'login', header: 'Login',
      cell: (p) => p.user
        ? <Badge variant={p.user.is_active ? 'success' : 'secondary'}>{p.user.is_active ? 'Active' : 'Disabled'}</Badge>
        : <span className="text-xs text-secondary-500">—</span>,
    },
    {
      key: 'actions', header: <span className="sr-only">Actions</span>, align: 'right',
      cell: (p) => (
        <div className="relative inline-block">
          <button type="button" aria-label="Row actions"
            onClick={(e) => { e.stopPropagation(); setOpenFor((v) => v === p.id ? null : p.id); }}
            className="rounded-button p-1.5 text-secondary-500 hover:bg-secondary-100">
            <MoreVertical className="h-4 w-4" />
          </button>
          {openFor === p.id && (
            <div role="menu" onClick={(e) => e.stopPropagation()}
              className="absolute right-0 z-10 mt-1 w-44 rounded-card border border-secondary-200 bg-surface py-1 text-sm shadow-card">
              <Link href={`${parentsBase}/${p.id}`} className="flex items-center gap-2 px-3 py-2 text-secondary-700 hover:bg-secondary-50"><Eye className="h-4 w-4 text-secondary-400" /> View profile</Link>
              {hasPermission('parent.edit') && (
                <Link href={`${parentsBase}/${p.id}/edit`} className="flex items-center gap-2 px-3 py-2 text-secondary-700 hover:bg-secondary-50"><Pencil className="h-4 w-4 text-secondary-400" /> Edit</Link>
              )}
              {p.is_active && hasPermission('parent.delete') && (
                <button onClick={() => { setOpenFor(null); setArchiveTarget(p); }}
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-danger hover:bg-danger-light"><ArchiveIcon className="h-4 w-4" /> Archive</button>
              )}
              {!p.is_active && hasPermission('parent.delete') && (
                <button onClick={() => { setOpenFor(null); setRestoreTarget(p); }}
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
        breadcrumb={[{ label: 'Home', href: dashboardBase }, { label: 'Parents' }]}
        title="Parents & Guardians"
        description="Manage parent profiles, portal accounts, and child relationships."
        actions={canCreate && (
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => router.push(`${parentsBase}/create`)}>Add parent</Button>
        )}
      />

      <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3">
        {[
          { label: 'Total active', value: stats?.total      ?? '—' },
          { label: 'With portal access', value: stats?.with_login ?? '—' },
          { label: 'Archived',     value: stats?.archived   ?? '—' },
        ].map((s) => (
          <Card key={s.label}><CardContent className="py-4"><p className="text-sm text-secondary-500">{s.label}</p><p className="text-2xl font-semibold text-ink">{s.value}</p></CardContent></Card>
        ))}
      </div>

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="min-w-64 flex-1">
          <Input placeholder="Search by name, email, or phone…" value={search}
            onChange={(e) => setSearch(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <label className="flex h-10 items-center gap-2 rounded-button border border-secondary-300 px-3 text-sm">
          <input type="checkbox" checked={showArchived} onChange={(event) => setShowArchived(event.target.checked)} />
          Show archived
        </label>
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(p) => p.id}
        isLoading={isLoading || (isFetching && !data)}
        emptyTitle="No parents found"
        emptyDescription={search ? 'Try a different search.' : 'Register your first parent to get started.'}
        emptyAction={canCreate && !search && (
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => router.push(`${parentsBase}/create`)}>Register first parent</Button>
        )}
        onRowClick={(p) => router.push(`${parentsBase}/${p.id}`)}
      />

      {data && data.meta.total > 0 && (
        <Pagination page={data.meta.page} lastPage={data.meta.last_page}
          total={data.meta.total} perPage={data.meta.per_page} onPageChange={setPage} />
      )}

      {archiveTarget && <ArchiveParentDialog parent={archiveTarget} open onClose={() => setArchiveTarget(null)} />}
      {restoreTarget && <RestoreParentDialog parent={restoreTarget} open onClose={() => setRestoreTarget(null)} />}
    </div>
  );
}
