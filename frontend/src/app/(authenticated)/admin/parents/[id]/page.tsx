'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { Pencil, Archive as ArchiveIcon, RotateCcw, ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { LinkChildPanel } from '@/components/parents/LinkChildPanel';
import { ArchiveParentDialog, RestoreParentDialog } from '@/components/parents/ParentArchiveDialogs';
import { useParent } from '@/hooks/parents';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils/cn';

type Tab = 'profile' | 'children' | 'history';

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="grid grid-cols-3 gap-2 py-2">
      <dt className="text-sm font-medium text-secondary-600">{label}</dt>
      <dd className="col-span-2 text-sm text-ink">{value ?? <span className="text-secondary-400">—</span>}</dd>
    </div>
  );
}

export default function ParentProfilePage() {
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const { data: parent, isLoading, isError } = useParent(id);
  const role = useAuthStore((s) => s.user?.role.name);
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const parentsBase = role === 'secretary' ? '/secretary/parents' : '/admin/parents';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const [tab, setTab] = useState<Tab>('profile');
  const [archiveOpen, setArchiveOpen] = useState(false);
  const [restoreOpen, setRestoreOpen] = useState(false);

  if (isLoading) return <div className="space-y-4"><Skeleton className="h-8 w-1/3" /><Skeleton className="h-32" /><Skeleton className="h-64" /></div>;
  if (isError || !parent) {
    return (
      <Card><CardContent className="py-12 text-center">
        <h2 className="text-lg font-semibold text-ink">Parent not found</h2>
        <Button className="mt-6" variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />}
          onClick={() => router.push(parentsBase)}>Back to parents</Button>
      </CardContent></Card>
    );
  }

  const tabs: { id: Tab; label: string }[] = [
    { id: 'profile',  label: 'Profile' },
    { id: 'children', label: `Children (${parent.children?.length ?? 0})` },
    { id: 'history',  label: 'History' },
  ];

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Parents', href: parentsBase },
          { label: parent.full_name },
        ]}
        title={parent.full_name}
        description={parent.email}
        actions={<>
          {hasPermission('parent.edit') && parent.is_active && (
            <Button variant="outline" leftIcon={<Pencil className="h-4 w-4" />}
              onClick={() => router.push(`${parentsBase}/${parent.id}/edit`)}>Edit</Button>
          )}
          {parent.is_active && hasPermission('parent.delete') && (
            <Button variant="danger" leftIcon={<ArchiveIcon className="h-4 w-4" />} onClick={() => setArchiveOpen(true)}>Archive</Button>
          )}
          {!parent.is_active && hasPermission('parent.delete') && (
            <Button leftIcon={<RotateCcw className="h-4 w-4" />} onClick={() => setRestoreOpen(true)}>Restore</Button>
          )}
        </>}
      />

      <Card className="mb-6">
        <CardContent className="flex items-center gap-6 py-6">
          <Avatar name={parent.full_name} size="lg" className="h-24 w-24 text-2xl" />
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-xl font-semibold text-ink">{parent.full_name}</h2>
              <Badge variant={parent.is_active ? 'success' : 'secondary'}>
                {parent.is_active ? 'Active' : 'Archived'}
              </Badge>
              {parent.user && parent.user.is_active && <Badge variant="info">Portal access</Badge>}
            </div>
            <p className="text-sm text-secondary-500 capitalize">{parent.gender} · {parent.occupation ?? 'No occupation recorded'}</p>
            {parent.workplace && <p className="text-xs text-secondary-500 mt-1">Works at {parent.workplace}</p>}
          </div>
        </CardContent>
      </Card>

      <div className="mb-4 border-b border-secondary-200">
        <nav className="flex gap-1" role="tablist">
          {tabs.map((t) => (
            <button key={t.id} role="tab" aria-selected={tab === t.id} onClick={() => setTab(t.id)}
              className={cn('px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                tab === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-secondary-500 hover:text-ink')}>
              {t.label}
            </button>
          ))}
        </nav>
      </div>

      {tab === 'profile' && (
        <Card><CardContent className="py-6">
          <dl className="divide-y divide-secondary-100">
            <Field label="First name"     value={parent.first_name} />
            <Field label="Last name"      value={parent.last_name} />
            <Field label="Middle name"    value={parent.middle_name} />
            <Field label="Gender"         value={<span className="capitalize">{parent.gender}</span>} />
            <Field label="Email"          value={parent.email} />
            <Field label="Phone"          value={parent.phone} />
            <Field label="Alternate phone" value={parent.alternate_phone} />
            <Field label="Address"        value={parent.address} />
            <Field label="City"           value={parent.city} />
            <Field label="Country"        value={parent.country} />
            <Field label="Occupation"     value={parent.occupation} />
            <Field label="Workplace"      value={parent.workplace} />
            {parent.national_id !== undefined && <Field label="National ID" value={parent.national_id} />}
          </dl>
        </CardContent></Card>
      )}

      {tab === 'children' && <LinkChildPanel parent={parent} />}

      {tab === 'history' && (
        <Card><CardContent className="py-6">
          <dl className="divide-y divide-secondary-100">
            <Field label="Created at"  value={parent.created_at ? new Date(parent.created_at).toLocaleString() : null} />
            <Field label="Archived at" value={parent.archived_at ? new Date(parent.archived_at).toLocaleString() : null} />
            <Field label="Portal login" value={parent.user ? parent.user.email : <span className="text-secondary-400">No portal access</span>} />
            <Field label="Last login"  value={parent.user?.last_login_at ? new Date(parent.user.last_login_at).toLocaleString() : null} />
          </dl>
        </CardContent></Card>
      )}

      <ArchiveParentDialog parent={parent} open={archiveOpen} onClose={() => setArchiveOpen(false)} />
      <RestoreParentDialog parent={parent} open={restoreOpen} onClose={() => setRestoreOpen(false)} />
    </div>
  );
}
