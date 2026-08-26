'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { PageHeader } from '@/components/ui/PageHeader';
import { TeacherForm } from '@/components/teachers/TeacherForm';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { useCreateTeacher } from '@/hooks/teachers';
import type { ApiError } from '@/types/api';
import type { CreateTeacherResult } from '@/types/teacher';
import type { AxiosError } from 'axios';

export default function CreateTeacherPage() {
  const router = useRouter();
  const create = useCreateTeacher();
  const [serverErrors, setServerErrors] = useState<Record<string, string[] | string> | undefined>();
  const [created, setCreated] = useState<CreateTeacherResult | null>(null);

  const continueToTeacher = () => {
    if (created) router.replace(`/admin/teachers/${created.teacher.id}`);
  };

  const copyCredentials = async () => {
    if (!created?.login_credentials) return;
    const { email, temporary_password } = created.login_credentials;
    await navigator.clipboard.writeText(`Email: ${email}\nTemporary password: ${temporary_password}`);
    toast.success('Login credentials copied.');
  };

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: '/admin/dashboard' },
          { label: 'Teachers', href: '/admin/teachers' },
          { label: 'New teacher' },
        ]}
        title="Register a new teacher"
        description="An employee number will be assigned automatically. If a login account is created, its temporary credentials will be shown once after registration."
      />
      <TeacherForm
        isSubmitting={create.isPending}
        serverErrors={serverErrors}
        submitLabel="Register teacher"
        onCancel={() => router.push('/admin/teachers')}
        onSubmit={async (payload) => {
          setServerErrors(undefined);
          try {
            const result = await create.mutateAsync(payload);
            toast.success(`${result.teacher.full_name} registered (${result.teacher.employee_number}).`);
            if (result.login_credentials) setCreated(result);
            else router.replace(`/admin/teachers/${result.teacher.id}`);
          } catch (err) {
            const body = (err as AxiosError<ApiError>).response?.data;
            if (body?.errors) { setServerErrors(body.errors); toast.error('Please correct the errors below.'); }
            else { toast.error(body?.message ?? 'Could not register the teacher.'); }
          }
        }}
      />
      <Modal
        open={created !== null}
        onClose={continueToTeacher}
        closeOnBackdrop={false}
        title="Teacher login account created"
        description="Save or give these credentials to the teacher. The password cannot be displayed again."
        footer={<>
          <Button variant="outline" onClick={copyCredentials}>Copy credentials</Button>
          <Button onClick={continueToTeacher}>Continue</Button>
        </>}
      >
        {created?.login_credentials && (
          <div className="space-y-4">
            <div className="rounded-lg border border-secondary-200 bg-secondary-50 p-4">
              <p className="text-xs font-medium uppercase tracking-wide text-secondary-500">Email</p>
              <p className="mt-1 select-all font-mono text-sm text-ink">{created.login_credentials.email}</p>
            </div>
            <div className="rounded-lg border border-warning/30 bg-warning-light p-4">
              <p className="text-xs font-medium uppercase tracking-wide text-secondary-600">Temporary password</p>
              <p className="mt-1 select-all font-mono text-base font-semibold text-ink">
                {created.login_credentials.temporary_password}
              </p>
            </div>
            <p className="text-sm text-secondary-600">
              This account will now appear under Admin → Users with the Teacher role.
            </p>
          </div>
        )}
      </Modal>
    </div>
  );
}
