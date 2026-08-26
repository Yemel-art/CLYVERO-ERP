'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { PageHeader } from '@/components/ui/PageHeader';
import { ParentForm } from '@/components/parents/ParentForm';
import { useCreateParent } from '@/hooks/parents';
import type { ApiError } from '@/types/api';
import type { AxiosError } from 'axios';
import { useAuthStore } from '@/store/auth';

export default function CreateParentPage() {
  const router = useRouter();
  const role = useAuthStore((s) => s.user?.role.name);
  const parentsBase = role === 'secretary' ? '/secretary/parents' : '/admin/parents';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const create = useCreateParent();
  const [serverErrors, setServerErrors] = useState<Record<string, string[] | string> | undefined>();

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Parents', href: parentsBase },
          { label: 'New parent' },
        ]}
        title="Register a new parent or guardian"
        description="After registration you can link their children from the profile page."
      />
      <ParentForm
        isSubmitting={create.isPending}
        serverErrors={serverErrors}
        submitLabel="Register parent"
        onCancel={() => router.push(parentsBase)}
        onSubmit={async (payload) => {
          setServerErrors(undefined);
          try {
            const p = await create.mutateAsync(payload);
            toast.success(`${p.full_name} registered.`);
            if (p.temporary_password) {
              window.alert(
                `Parent portal account created.\n\nEmail: ${p.email}\nTemporary password: ${p.temporary_password}\n\nPlease give these credentials to the parent now.`,
              );
            }
            router.replace(`${parentsBase}/${p.id}`);
          } catch (err) {
            const body = (err as AxiosError<ApiError>).response?.data;
            if (body?.errors) { setServerErrors(body.errors); toast.error('Please correct the errors below.'); }
            else { toast.error(body?.message ?? 'Could not register the parent.'); }
          }
        }}
      />
    </div>
  );
}
