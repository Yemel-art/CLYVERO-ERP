'use client';

import { useState, useMemo, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Plus, Search, SlidersHorizontal, MoreVertical, Eye, Pencil, Archive as ArchiveIcon, RotateCcw, Upload } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { Pagination } from '@/components/ui/Pagination';
import { Avatar } from '@/components/ui/Avatar';
import { StudentStatusBadge } from '@/components/students/StudentStatusBadge';
import { StudentFiltersDrawer } from '@/components/students/StudentFiltersDrawer';
import { ArchiveStudentDialog, RestoreStudentDialog } from '@/components/students/StudentArchiveDialogs';
import { useStudents, useStudentStatistics } from '@/hooks/students';
import { useAuthStore } from '@/store/auth';
import type { Student, StudentFilters } from '@/types/student';

function useDebounced<T>(value: T, ms = 300): T {
  const [debounced, set] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => set(value), ms);
    return () => clearTimeout(id);
  }, [value, ms]);
  return debounced;
}

export default function StudentsListPage() {
  const router = useRouter();
  const role = useAuthStore((state) => state.user?.role.name);
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const studentsBase = role === 'secretary' ? '/secretary/students' : '/admin/students';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const canCreate = hasPermission('student.create');

  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<Partial<StudentFilters>>({});
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [archiveTarget, setArchiveTarget] = useState<Student | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<Student | null>(null);
  const [actionsOpenFor, setActionsOpenFor] = useState<string | null>(null);

  const debouncedQ = useDebounced(search, 300);

  const queryFilters: StudentFilters = useMemo(() => ({
    q: debouncedQ || undefined,
    page,
    per_page: 20,
    ...filters,
  }), [debouncedQ, page, filters]);

  // Reset to page 1 whenever filters or search change.
  useEffect(() => { setPage(1); }, [debouncedQ, filters]);

  const { data, isLoading, isFetching } = useStudents(queryFilters);
  const { data: stats } = useStudentStatistics();

  const columns: Column<Student>[] = [
    {
      key: 'name',
      header: 'Student',
      cell: (s) => (
        <div className="flex items-center gap-3">
          {s.photo_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={s.photo_url} alt="" className="h-9 w-9 rounded-full object-cover" />
          ) : (
            <Avatar name={s.full_name} size="sm" />
          )}
          <div>
            <p className="font-medium text-ink">{s.full_name}</p>
            <p className="text-xs text-secondary-500">{s.admission_number}</p>
          </div>
        </div>
      ),
    },
    { key: 'gender', header: 'Gender', cell: (s) => <span className="capitalize">{s.gender}</span> },
    { key: 'age',    header: 'Age',    cell: (s) => `${s.age} yrs`, align: 'right' },
    { key: 'enrolled', header: 'Enrolled', cell: (s) => s.enrollment_date },
    { key: 'status', header: 'Status', cell: (s) => <StudentStatusBadge status={s.status} /> },
    {
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      align: 'right',
      cell: (s) => (
        <div className="relative inline-block">
          <button
            type="button"
            aria-label="Row actions"
            onClick={(e) => { e.stopPropagation(); setActionsOpenFor((v) => v === s.id ? null : s.id); }}
            className="rounded-button p-1.5 text-secondary-500 hover:bg-secondary-100"
          >
            <MoreVertical className="h-4 w-4" />
          </button>
          {actionsOpenFor === s.id && (
            <div
              role="menu"
              onClick={(e) => e.stopPropagation()}
              className="absolute right-0 z-10 mt-1 w-44 origin-top-right rounded-card border border-secondary-200 bg-surface py-1 text-sm shadow-card"
            >
              <Link href={`${studentsBase}/${s.id}`} className="flex items-center gap-2 px-3 py-2 text-secondary-700 hover:bg-secondary-50" role="menuitem">
                <Eye className="h-4 w-4 text-secondary-400" /> View profile
              </Link>
              {hasPermission('student.edit') && (
                <Link href={`${studentsBase}/${s.id}/edit`} className="flex items-center gap-2 px-3 py-2 text-secondary-700 hover:bg-secondary-50" role="menuitem">
                  <Pencil className="h-4 w-4 text-secondary-400" /> Edit
                </Link>
              )}
              {s.status !== 'archived' && hasPermission('student.archive') && (
                <button onClick={() => { setActionsOpenFor(null); setArchiveTarget(s); }}
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-danger hover:bg-danger-light" role="menuitem">
                  <ArchiveIcon className="h-4 w-4" /> Archive
                </button>
              )}
              {s.status === 'archived' && hasPermission('student.restore') && (
                <button onClick={() => { setActionsOpenFor(null); setRestoreTarget(s); }}
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-primary-700 hover:bg-primary-50" role="menuitem">
                  <RotateCcw className="h-4 w-4" /> Restore
                </button>
              )}
            </div>
          )}
        </div>
      ),
    },
  ];

  // Close the row menu on outside click.
  useEffect(() => {
    if (!actionsOpenFor) return;
    const handler = () => setActionsOpenFor(null);
    document.addEventListener('click', handler);
    return () => document.removeEventListener('click', handler);
  }, [actionsOpenFor]);

  const activeFilterCount = Object.values(filters).filter((v) => v !== undefined && v !== '' && v !== false).length;

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: dashboardBase }, { label: 'Students' }]}
        title="Students"
        description="Manage student records, profiles, and enrollment."
        actions={
          canCreate && <div className="flex gap-2"><Button variant="outline" leftIcon={<Upload className="h-4 w-4" />} onClick={() => router.push(`${studentsBase}/import`)}>Import spreadsheet</Button><Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => router.push(`${studentsBase}/create`)}>Add student</Button></div>
        }
      />

      {/* Stat cards */}
      <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        {[
          { label: 'Active',     value: stats?.total      ?? '—' },
          { label: 'Archived',   value: stats?.archived   ?? '—' },
          { label: 'Graduated',  value: stats?.graduated  ?? '—' },
          { label: 'Withdrawn',  value: stats?.withdrawn  ?? '—' },
        ].map((s) => (
          <Card key={s.label}>
            <CardContent className="py-4">
              <p className="text-sm text-secondary-500">{s.label}</p>
              <p className="text-2xl font-semibold text-ink">{s.value}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Search + filters bar */}
      <div className="mb-4 flex items-center gap-3">
        <div className="flex-1">
          <Input
            placeholder="Search by name, admission number, or email…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            leftIcon={<Search className="h-4 w-4" />}
          />
        </div>
        <Button
          variant="outline"
          leftIcon={<SlidersHorizontal className="h-4 w-4" />}
          onClick={() => setFiltersOpen(true)}
        >
          Filters{activeFilterCount > 0 && <span className="ml-1.5 rounded-full bg-primary-600 px-1.5 py-0.5 text-xs text-white">{activeFilterCount}</span>}
        </Button>
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(s) => s.id}
        isLoading={isLoading || (isFetching && !data)}
        emptyTitle="No students found"
        emptyDescription={search ? 'Try adjusting your search or filters.' : 'Get started by registering your first student.'}
        emptyAction={canCreate && !search && (
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => router.push(`${studentsBase}/create`)}>
            Register first student
          </Button>
        )}
        onRowClick={(s) => router.push(`${studentsBase}/${s.id}`)}
      />

      {data && data.meta.total > 0 && (
        <Pagination
          page={data.meta.page}
          lastPage={data.meta.last_page}
          total={data.meta.total}
          perPage={data.meta.per_page}
          onPageChange={setPage}
        />
      )}

      <StudentFiltersDrawer
        open={filtersOpen}
        onClose={() => setFiltersOpen(false)}
        initial={filters}
        onApply={setFilters}
      />

      {archiveTarget && (
        <ArchiveStudentDialog
          student={archiveTarget}
          open
          onClose={() => setArchiveTarget(null)}
        />
      )}
      {restoreTarget && (
        <RestoreStudentDialog
          student={restoreTarget}
          open
          onClose={() => setRestoreTarget(null)}
        />
      )}
    </div>
  );
}
