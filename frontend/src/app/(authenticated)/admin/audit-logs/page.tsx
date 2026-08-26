'use client';

import { useState, useEffect } from 'react';
import { ShieldCheck, ChevronDown, ChevronUp } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAuditLogs } from '@/hooks/admin';

const MODULES = [
  'student', 'teacher', 'parent', 'class', 'subject',
  'academic_year', 'term', 'attendance', 'grades', 'timetable',
  'finance', 'user', 'role', 'school',
];

const actionColor: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'secondary'> = {
  create: 'success', update: 'info', delete: 'danger',
  archive: 'warning', restore: 'success', login: 'info',
  logout: 'secondary', publish: 'success', activate: 'success', close: 'warning',
};

export default function AuditLogsPage() {
  const [moduleFilter, setModuleFilter] = useState('');
  const [actionFilter, setActionFilter] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);
  useEffect(() => { setPage(1); }, [moduleFilter, actionFilter, from, to]);

  const { data, isLoading } = useAuditLogs({
    module: moduleFilter || undefined,
    action: actionFilter || undefined,
    from: from || undefined,
    to: to || undefined,
    page,
    per_page: 50,
  });

  const [expanded, setExpanded] = useState<Set<string>>(new Set());
  const toggle = (id: string) => setExpanded((prev) => {
    const next = new Set(prev);
    if (next.has(id)) next.delete(id); else next.add(id);
    return next;
  });

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: '/admin/dashboard' }, { label: 'Audit logs' }]}
        title="Audit logs"
        description="Every consequential action is recorded here. Entries are immutable."
      />

      <Card className="mb-4">
        <CardContent className="flex flex-wrap items-end gap-3 py-3">
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">Module</label>
            <select value={moduleFilter} onChange={(e) => setModuleFilter(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">All</option>
              {MODULES.map((m) => <option key={m} value={m}>{m}</option>)}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">Action</label>
            <Input value={actionFilter} onChange={(e) => setActionFilter(e.target.value)} placeholder="e.g. create" />
          </div>
          <Input type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} />
          <Input type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} />
        </CardContent>
      </Card>

      {isLoading ? <Skeleton className="h-64" /> : (
        <Card>
          <CardContent className="py-2">
            {(data?.data ?? []).length === 0 ? (
              <p className="py-12 text-center text-sm text-secondary-500">No audit entries match these filters.</p>
            ) : (
              <ul className="divide-y divide-secondary-100">
                {(data?.data ?? []).map((row) => {
                  const isOpen = expanded.has(row.id);
                  return (
                    <li key={row.id}>
                      <button onClick={() => toggle(row.id)}
                        className="flex w-full items-center justify-between py-3 text-left text-sm hover:bg-secondary-50">
                        <div className="flex items-center gap-3">
                          <ShieldCheck className="h-4 w-4 text-secondary-400" />
                          <div>
                            <p className="font-medium text-ink">
                              {row.user?.full_name ?? 'System'}
                              <span className="ml-2 font-normal text-secondary-500">{row.module}</span>
                            </p>
                            <p className="text-xs text-secondary-500">
                              {new Date(row.created_at).toLocaleString()}{row.ip ? ` · ${row.ip}` : ''}
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <Badge variant={actionColor[row.action] ?? 'secondary'}>{row.action}</Badge>
                          {isOpen ? <ChevronUp className="h-4 w-4 text-secondary-400" /> : <ChevronDown className="h-4 w-4 text-secondary-400" />}
                        </div>
                      </button>
                      {isOpen && (
                        <div className="ml-7 mb-3 rounded-card border border-secondary-100 bg-secondary-50 p-3 text-xs font-mono text-secondary-700">
                          <p><span className="font-medium">Subject:</span> {row.subject_type ?? '—'} {row.subject_id ?? ''}</p>
                          <p><span className="font-medium">User agent:</span> {row.user_agent ?? '—'}</p>
                          {row.metadata && Object.keys(row.metadata).length > 0 && (
                            <pre className="mt-2 whitespace-pre-wrap break-words">{JSON.stringify(row.metadata, null, 2)}</pre>
                          )}
                        </div>
                      )}
                    </li>
                  );
                })}
              </ul>
            )}
          </CardContent>
        </Card>
      )}

      {data && data.meta.total > 0 && (
        <Pagination page={data.meta.page} lastPage={data.meta.last_page}
          total={data.meta.total} perPage={data.meta.per_page} onPageChange={setPage} />
      )}
    </div>
  );
}
