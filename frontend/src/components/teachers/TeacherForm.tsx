'use client';

import { useEffect } from 'react';
import { useForm, type SubmitHandler } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { teacherSchema, cleanTeacherPayload, type TeacherFormValues } from '@/lib/validation/teacher';
import type { Teacher } from '@/types/teacher';

interface Props {
  initial?: Teacher;
  isSubmitting?: boolean;
  isEdit?: boolean;
  submitLabel?: string;
  onSubmit: (payload: Record<string, unknown>) => Promise<void> | void;
  onCancel?: () => void;
  serverErrors?: Record<string, string[] | string>;
}

function defaultsFor(initial?: Teacher): TeacherFormValues {
  return {
    first_name:    initial?.first_name ?? '',
    last_name:     initial?.last_name ?? '',
    middle_name:   initial?.middle_name ?? '',
    gender:        initial?.gender ?? 'male',
    email:         initial?.email ?? '',
    phone:         initial?.phone ?? '',
    date_of_birth: initial?.date_of_birth ?? '',
    nationality:   initial?.nationality ?? 'Cameroonian',
    address:       initial?.address ?? '',
    city:          initial?.city ?? '',
    country:       initial?.country ?? 'Cameroon',
    qualification: initial?.qualification ?? '',
    specialization: initial?.specialization ?? '',
    position:      initial?.position ?? '',
    department:    initial?.department ?? '',
    years_of_experience: initial?.years_of_experience ?? 0,
    hire_date:     initial?.hire_date ?? new Date().toISOString().slice(0, 10),
    salary:        initial?.salary ?? undefined,
    emergency_contact_name:  initial?.emergency_contact?.name ?? '',
    emergency_contact_phone: initial?.emergency_contact?.phone ?? '',
    create_user_account: true,
  };
}

export function TeacherForm({ initial, isSubmitting, isEdit, submitLabel = 'Save teacher', onSubmit, onCancel, serverErrors }: Props) {
  const {
    register, handleSubmit, formState: { errors }, setError, clearErrors,
  } = useForm<TeacherFormValues>({
    resolver: zodResolver(teacherSchema),
    defaultValues: defaultsFor(initial),
  });

  useEffect(() => {
    if (!serverErrors) {
      clearErrors();
      return;
    }
    for (const [field, messages] of Object.entries(serverErrors)) {
      const message = Array.isArray(messages) ? messages[0] : messages;
      if (message) setError(field as keyof TeacherFormValues, { type: 'server', message });
    }
  }, [clearErrors, serverErrors, setError]);

  const submit: SubmitHandler<TeacherFormValues> = async (values) => {
    await onSubmit(cleanTeacherPayload(values));
  };

  return (
    <form onSubmit={handleSubmit(submit)} className="space-y-6" noValidate>
      {Object.keys(errors).length > 0 && (
        <div role="alert" className="rounded-card border border-danger/30 bg-danger-light p-4 text-sm text-danger">
          <p className="font-semibold">The teacher could not be registered yet.</p>
          <p className="mt-1">Please correct the highlighted field(s). The exact reason is displayed below each field.</p>
        </div>
      )}
      <Card>
        <CardHeader>
          <CardTitle>Identity</CardTitle>
          <CardDescription>Personal information of the teacher.</CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input label="First name" required error={errors.first_name?.message} {...register('first_name')} />
          <Input label="Last name"  required error={errors.last_name?.message}  {...register('last_name')} />
          <Input label="Middle name" error={errors.middle_name?.message} {...register('middle_name')} />
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Gender <span className="text-danger">*</span></label>
            <select {...register('gender')} className="h-10 rounded-input border border-secondary-300 px-3 text-sm">
              <option value="male">Male</option>
              <option value="female">Female</option>
            </select>
          </div>
          <Input type="date" label="Date of birth" error={errors.date_of_birth?.message} {...register('date_of_birth')} />
          <Input label="Nationality" error={errors.nationality?.message} {...register('nationality')} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Contact</CardTitle></CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input type="email" label="Email" required error={errors.email?.message} {...register('email')} />
          <Input label="Phone" placeholder="+237..." error={errors.phone?.message} {...register('phone')} />
          <div className="md:col-span-2">
            <Input label="Address" error={errors.address?.message} {...register('address')} />
          </div>
          <Input label="City" error={errors.city?.message} {...register('city')} />
          <Input label="Country" error={errors.country?.message} {...register('country')} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Employment</CardTitle>
          <CardDescription>Qualifications, hire date, and (optional) salary.</CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input label="Qualification" placeholder="e.g. MSc Mathematics" error={errors.qualification?.message} {...register('qualification')} />
          <Input label="Specialization" error={errors.specialization?.message} {...register('specialization')} />
          <Input label="Position" placeholder="e.g. Senior Teacher" error={errors.position?.message} {...register('position')} />
          <Input label="Department" placeholder="e.g. Science Department" error={errors.department?.message} {...register('department')} />
          <Input type="number" label="Years of experience" min={0} max={60} error={errors.years_of_experience?.message} {...register('years_of_experience')} />
          <Input type="date" label="Hire date" required error={errors.hire_date?.message} {...register('hire_date')} />
          <Input type="number" label="Salary (XAF, optional)" min={0} step="1000" error={errors.salary?.message} {...register('salary')} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Emergency contact</CardTitle></CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input label="Contact name" error={errors.emergency_contact_name?.message} {...register('emergency_contact_name')} />
          <Input label="Contact phone" error={errors.emergency_contact_phone?.message} {...register('emergency_contact_phone')} />
        </CardContent>
      </Card>

      {!isEdit && (
        <label className="flex items-center gap-2 text-sm text-secondary-700">
          <input type="checkbox" {...register('create_user_account')}
            className="rounded border-secondary-300 text-primary-600" />
          Create a login account for this teacher (the temporary credentials will be shown once after registration).
        </label>
      )}

      <div className="flex items-center justify-end gap-3">
        {onCancel && <Button type="button" variant="outline" onClick={onCancel}>Cancel</Button>}
        <Button type="submit" isLoading={isSubmitting}>{submitLabel}</Button>
      </div>
    </form>
  );
}
