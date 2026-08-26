'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { toast } from 'sonner';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/Skeleton';
import { ParentForm } from '@/components/parents/ParentForm';
import { useParent, useUpdateParent } from '@/hooks/parents';
import type { ApiError } from '@/types/api';
import type { AxiosError } from 'axios';
import { useAuthStore } from '@/store/auth';

export default function EditParentPage() {
  const router = useRouter();
  const role = useAuthStore((s) => s.user?.role.name);
  const parentsBase = role === 'secretary' ? '/secretary/parents' : '/admin/parents';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const { id } = useParams<{ id: string }>();
  const { data: parent, isLoading } = useParent(id);
  const update = useUpdateParent(id);
  const [serverErrors, setServerErrors] = useState<Record<string, string[] | string> | undefined>();

  if (isLoading || !parent) {
    return <div className="mx-auto max-w-4xl space-y-4"><Skeleton className="h-8 w-1/3" /><Skeleton className="h-96" /></div>;
  }

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Parents', href: parentsBase },
          { label: parent.full_name, href: `${parentsBase}/${parent.id}` },
          { label: 'Edit' },
        ]}
        title={`Edit ${parent.full_name}`}
      />
      <ParentForm
        initial={parent}
        isEdit
        isSubmitting={update.isPending}
        serverErrors={serverErrors}
        submitLabel="Save changes"
        onCancel={() => router.push(`${parentsBase}/${parent.id}`)}
        onSubmit={async (payload) => {
          setServerErrors(undefined);
          try {
            await update.mutateAsync(payload);
            toast.success('Parent updated.');
            router.replace(`${parentsBase}/${parent.id}`);
          } catch (err) {
            const body = (err as AxiosError<ApiError>).response?.data;
            if (body?.errors) { setServerErrors(body.errors); toast.error('Please correct the errors below.'); }
            else { toast.error(body?.message ?? 'Could not save changes.'); }
          }
        }}
      />
    </div>
  );
}
