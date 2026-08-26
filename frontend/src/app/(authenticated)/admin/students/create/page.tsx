'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import type { AxiosError } from 'axios';
import { PageHeader } from '@/components/ui/PageHeader';
import { StudentForm } from '@/components/students/StudentForm';
import { useCreateStudent } from '@/hooks/students';
import { useAuthStore } from '@/store/auth';
import type { ApiError } from '@/types/api';

export default function CreateStudentPage() {
  const router = useRouter();
  const role = useAuthStore((state) => state.user?.role.name);
  const teacherMode = role === 'teacher';
  const secretaryMode = role === 'secretary';
  const basePath = teacherMode ? '/teacher' : secretaryMode ? '/secretary' : '/admin';
  const studentsPath = secretaryMode ? '/secretary/students' : '/admin/students';
  const create = useCreateStudent();
  const [serverErrors, setServerErrors] = useState<Record<string, string[] | string> | undefined>();

  const onSubmit = async (payload: Record<string, unknown>) => {
    setServerErrors(undefined);
    try {
      const student = await create.mutateAsync(payload);
      toast.success(`${student.full_name} registered (${student.admission_number}).`);
      if (student.parent_credentials) {
        window.alert(`Parent portal account created.\n\nEmail: ${student.parent_credentials.email}\nTemporary password: ${student.parent_credentials.temporary_password}\n\nPlease give these credentials to the parent now.`);
      }
      router.replace(teacherMode ? '/teacher/classes' : `${studentsPath}/${student.id}`);
    } catch (error) {
      const body = (error as AxiosError<ApiError>).response?.data;
      if (body?.errors) {
        setServerErrors(body.errors);
        toast.error('Please correct the errors below.');
      } else {
        toast.error(body?.message ?? 'Could not register the student. Please try again.');
      }
    }
  };

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: `${basePath}/dashboard` },
          { label: teacherMode ? 'My Classes' : 'Students', href: teacherMode ? '/teacher/classes' : studentsPath },
          { label: 'New student' },
        ]}
        title="Register a new student"
        description={teacherMode
          ? 'Register a student in one of your assigned classes using the official matriculation number.'
          : "Enter the student's official matriculation number and enrollment information."}
      />
      <StudentForm
        onSubmit={onSubmit}
        onCancel={() => router.push(teacherMode ? '/teacher/classes' : studentsPath)}
        isSubmitting={create.isPending}
        serverErrors={serverErrors}
        submitLabel="Register student"
      />
    </div>
  );
}
