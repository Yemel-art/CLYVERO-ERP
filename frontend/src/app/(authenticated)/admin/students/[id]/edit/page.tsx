'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { toast } from 'sonner';
import { PageHeader } from '@/components/ui/PageHeader';
import { StudentForm } from '@/components/students/StudentForm';
import { Skeleton } from '@/components/ui/Skeleton';
import { useStudent, useUpdateStudent } from '@/hooks/students';
import { useAuthStore } from '@/store/auth';
import type { ApiError } from '@/types/api';
import type { AxiosError } from 'axios';

export default function EditStudentPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const id = params.id;
  const role = useAuthStore((state) => state.user?.role.name);
  const studentsBase = role === 'secretary' ? '/secretary/students' : '/admin/students';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const { data: student, isLoading } = useStudent(id);
  const update = useUpdateStudent(id);
  const [serverErrors, setServerErrors] = useState<Record<string, string[] | string> | undefined>();

  const onSubmit = async (payload: Record<string, unknown>) => {
    setServerErrors(undefined);
    try {
      await update.mutateAsync(payload);
      toast.success('Student updated successfully.');
      router.replace(`${studentsBase}/${id}`);
    } catch (err) {
      const axiosErr = err as AxiosError<ApiError>;
      const body = axiosErr.response?.data;
      if (body?.errors) {
        setServerErrors(body.errors);
        toast.error('Please correct the errors below.');
      } else {
        toast.error(body?.message ?? 'Could not save changes.');
      }
    }
  };

  if (isLoading || !student) {
    return (
      <div className="mx-auto max-w-4xl space-y-4">
        <Skeleton className="h-8 w-1/3" />
        <Skeleton className="h-96" />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Students', href: studentsBase },
          { label: student.full_name, href: `${studentsBase}/${student.id}` },
          { label: 'Edit' },
        ]}
        title={`Edit ${student.full_name}`}
        description={`Admission number: ${student.admission_number}`}
      />
      <StudentForm
        initial={student}
        onSubmit={onSubmit}
        onCancel={() => router.push(`${studentsBase}/${student.id}`)}
        isSubmitting={update.isPending}
        serverErrors={serverErrors}
        submitLabel="Save changes"
      />
    </div>
  );
}
