'use client';

import { useForm, type SubmitHandler } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { parentSchema, cleanParentPayload, type ParentFormValues } from '@/lib/validation/parent';
import type { ParentGuardian } from '@/types/parent';

interface Props {
  initial?: ParentGuardian;
  isEdit?: boolean;
  isSubmitting?: boolean;
  submitLabel?: string;
  onSubmit: (payload: Record<string, unknown>) => Promise<void> | void;
  onCancel?: () => void;
  serverErrors?: Record<string, string[] | string>;
}

function defaultsFor(initial?: ParentGuardian): ParentFormValues {
  return {
    first_name:      initial?.first_name ?? '',
    last_name:       initial?.last_name ?? '',
    middle_name:     initial?.middle_name ?? '',
    gender:          initial?.gender ?? 'female',
    email:           initial?.email ?? '',
    phone:           initial?.phone ?? '',
    alternate_phone: initial?.alternate_phone ?? '',
    address:         initial?.address ?? '',
    city:            initial?.city ?? 'Yaoundé',
    country:         initial?.country ?? 'Cameroon',
    occupation:      initial?.occupation ?? '',
    workplace:       initial?.workplace ?? '',
    national_id:     initial?.national_id ?? '',
    is_active:       initial?.is_active ?? true,
    create_user_account: true,
  };
}

export function ParentForm({ initial, isEdit, isSubmitting, submitLabel = 'Save parent', onSubmit, onCancel, serverErrors }: Props) {
  const {
    register, handleSubmit, formState: { errors }, setError,
  } = useForm<ParentFormValues>({
    resolver: zodResolver(parentSchema),
    defaultValues: defaultsFor(initial),
  });

  if (serverErrors) {
    for (const [field, msgs] of Object.entries(serverErrors)) {
      const msg = Array.isArray(msgs) ? msgs[0] : msgs;
      if (msg) setError(field as keyof ParentFormValues, { type: 'server', message: msg });
    }
  }

  const submit: SubmitHandler<ParentFormValues> = async (values) => {
    await onSubmit(cleanParentPayload(values));
  };

  return (
    <form onSubmit={handleSubmit(submit)} className="space-y-6" noValidate>
      <Card>
        <CardHeader><CardTitle>Identity</CardTitle><CardDescription>Personal information of the parent or guardian.</CardDescription></CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input label="First name" required error={errors.first_name?.message} {...register('first_name')} />
          <Input label="Last name" required error={errors.last_name?.message} {...register('last_name')} />
          <Input label="Middle name" error={errors.middle_name?.message} {...register('middle_name')} />
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Gender <span className="text-danger">*</span></label>
            <select {...register('gender')} className="h-10 rounded-input border border-secondary-300 px-3 text-sm">
              <option value="female">Female</option>
              <option value="male">Male</option>
            </select>
          </div>
          <Input label="National ID" placeholder="Optional" error={errors.national_id?.message} {...register('national_id')} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Contact</CardTitle></CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input type="email" label="Email" required error={errors.email?.message} {...register('email')} />
          <Input label="Phone" required placeholder="+237..." error={errors.phone?.message} {...register('phone')} />
          <Input label="Alternate phone" error={errors.alternate_phone?.message} {...register('alternate_phone')} />
          <div className="md:col-span-2">
            <Input label="Address" error={errors.address?.message} {...register('address')} />
          </div>
          <Input label="City" error={errors.city?.message} {...register('city')} />
          <Input label="Country" error={errors.country?.message} {...register('country')} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Occupation</CardTitle></CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input label="Occupation" error={errors.occupation?.message} {...register('occupation')} />
          <Input label="Workplace" error={errors.workplace?.message} {...register('workplace')} />
        </CardContent>
      </Card>

      {!isEdit && (
        <label className="flex items-center gap-2 text-sm text-secondary-700">
          <input type="checkbox" defaultChecked {...register('create_user_account')}
            className="rounded border-secondary-300 text-primary-600" />
          Create a parent portal login. A temporary password will be generated.
        </label>
      )}

      <div className="flex items-center justify-end gap-3">
        {onCancel && <Button type="button" variant="outline" onClick={onCancel}>Cancel</Button>}
        <Button type="submit" isLoading={isSubmitting}>{submitLabel}</Button>
      </div>
    </form>
  );
}
