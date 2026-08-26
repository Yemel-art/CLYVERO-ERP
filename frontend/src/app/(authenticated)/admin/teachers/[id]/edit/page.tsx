'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { toast } from 'sonner';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/Skeleton';
import { TeacherForm } from '@/components/teachers/TeacherForm';
import { useTeacher, useUpdateTeacher } from '@/hooks/teachers';
import type { ApiError } from '@/types/api';
import type { AxiosError } from 'axios';

export default function EditTeacherPage() {
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const { data: teacher, isLoading } = useTeacher(id);
  const update = useUpdateTeacher(id);
  const [serverErrors, setServerErrors] = useState<Record<string, string[] | string> | undefined>();

  if (isLoading || !teacher) {
    return <div className="mx-auto max-w-4xl space-y-4"><Skeleton className="h-8 w-1/3" /><Skeleton className="h-96" /></div>;
  }

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: '/admin/dashboard' },
          { label: 'Teachers', href: '/admin/teachers' },
          { label: teacher.full_name, href: `/admin/teachers/${teacher.id}` },
          { label: 'Edit' },
        ]}
        title={`Edit ${teacher.full_name}`}
        description={`Employee number: ${teacher.employee_number}`}
      />
      <TeacherForm
        initial={teacher}
        isEdit
        isSubmitting={update.isPending}
        serverErrors={serverErrors}
        submitLabel="Save changes"
        onCancel={() => router.push(`/admin/teachers/${teacher.id}`)}
        onSubmit={async (payload) => {
          setServerErrors(undefined);
          try {
            await update.mutateAsync(payload);
            toast.success('Teacher updated.');
            router.replace(`/admin/teachers/${teacher.id}`);
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
