'use client';

import { useEffect, useMemo } from 'react';
import Link from 'next/link';
import { useForm, type SubmitHandler } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { studentSchema, type StudentFormValues, cleanStudentPayload } from '@/lib/validation/student';
import { GENDER_OPTIONS } from '@/types/student';
import {
  CYCLES,
} from '@/config/student';
import { useAcademicYears, useClasses } from '@/hooks/academic';
import { useParents } from '@/hooks/parents';
import { useFees } from '@/hooks/finance';
import { useAuthStore } from '@/store/auth';
import type { Student } from '@/types/student';

interface Props {
  initial?: Student;
  isSubmitting?: boolean;
  submitLabel?: string;
  onSubmit: (payload: Record<string, unknown>) => Promise<void> | void;
  onCancel?: () => void;
  serverErrors?: Record<string, string[] | string>;
}

function defaultsFor(initial?: Student): StudentFormValues {
  return {
    admission_number: initial?.admission_number ?? '',
    first_name:      initial?.first_name ?? '',
    last_name:       initial?.last_name ?? '',
    middle_name:     initial?.middle_name ?? '',
    gender:          initial?.gender ?? 'male',
    date_of_birth:   initial?.date_of_birth ?? '',
    place_of_birth:  initial?.place_of_birth ?? '',
    nationality:     initial?.nationality ?? 'Cameroonian',
    religion:        initial?.religion ?? '',
    email:           initial?.email ?? '',
    phone:           initial?.phone ?? '',
    address:         initial?.address ?? '',
    city:            initial?.city ?? '',
    country:         initial?.country ?? 'Cameroon',
    parent_id:       initial?.parent_id ?? '',
    guardian_mode:   initial ? undefined : 'new',
    existing_parent_id: initial?.parent_id ?? '',
    parent_first_name: '',
    parent_last_name: '',
    parent_gender: 'female',
    parent_email: '',
    parent_phone: '',
    parent_relationship: '',
    create_parent_account: true,
    class_id:        initial?.class_id ?? '',
    academic_year_id: initial?.academic_year_id ?? '',
    enrollment_date: initial?.enrollment_date ?? new Date().toISOString().slice(0, 10),
    previous_school: initial?.previous_school ?? '',
    initial_payment: 0,
    // ─── Cycle & speciality ────────────────────────────────────────
    cycle:           initial?.cycle ?? 'secondary_general',
    speciality:      initial?.speciality ?? '',
    emergency_contact_name:         initial?.emergency_contact?.name ?? '',
    emergency_contact_phone:        initial?.emergency_contact?.phone ?? '',
    emergency_contact_relationship: initial?.emergency_contact?.relationship ?? '',
    blood_group:        initial?.health?.blood_group ?? '',
    allergies:          initial?.health?.allergies ?? '',
    medical_conditions: initial?.health?.medical_conditions ?? '',
  };
}

export function StudentForm({ initial, isSubmitting, submitLabel = 'Save student', onSubmit, onCancel, serverErrors }: Props) {
  const role = useAuthStore((state) => state.user?.role.name);
  const isTeacher = role === 'teacher';
  const isAdministrator = role === 'administrator';
  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
    setError,
    setValue,
  } = useForm<StudentFormValues>({
    resolver: zodResolver(studentSchema),
    defaultValues: defaultsFor(initial),
  });

  // Apply server-side validation errors after render. Calling setError while
  // React is rendering can create an update loop and make shared secretary
  // pages appear to freeze before AuthGuard sends them back to the dashboard.
  useEffect(() => {
    if (!serverErrors) return;
    for (const [field, messages] of Object.entries(serverErrors)) {
      const message = Array.isArray(messages) ? messages[0] : messages;
      if (message) setError(field as keyof StudentFormValues, { type: 'server', message });
    }
  }, [serverErrors, setError]);

  // ── Academic year and class data ─────────────────────────────
  const { data: academicYears } = useAcademicYears();
  const selectedYearId = watch('academic_year_id');
  const { data: classes } = useClasses(
    selectedYearId ? { academic_year_id: selectedYearId } : {},
  );
  const { data: parentsData } = useParents({ per_page: 200 }, !isTeacher);
  const { data: feeStructures } = useFees(
    selectedYearId ? { academic_year_id: selectedYearId } : {},
    !isTeacher,
  );

  // ── Watch cycle for conditional speciality ───────────────────
  const selectedCycle = watch('cycle');
  const guardianMode = watch('guardian_mode');
  const selectedClassId = watch('class_id');
  const selectedClass = useMemo(
    () => (classes ?? []).find((schoolClass) => schoolClass.id === selectedClassId),
    [classes, selectedClassId],
  );
  const configuredFeeTotal = useMemo(
    () => (feeStructures ?? [])
      .filter((fee) => fee.is_required && (!fee.class_id || fee.class_id === selectedClassId))
      .reduce((total, fee) => total + Number(fee.amount), 0),
    [feeStructures, selectedClassId],
  );

  const activeYears = useMemo(
    () => (academicYears ?? []).filter((y) => y.status === 'active' || y.status === 'upcoming'),
    [academicYears],
  );
  const eligibleClasses = useMemo(
    () => (classes ?? []).filter((schoolClass) => {
      if (!schoolClass.is_active) return false;
      if (schoolClass.id === initial?.class_id) return true;
      return schoolClass.education_system === selectedCycle;
    }),
    [classes, initial?.class_id, selectedCycle],
  );

  // New students should immediately use the current academic year, making
  // the class selector available instead of silently saving an unassigned pupil.
  useEffect(() => {
    if (initial || selectedYearId || activeYears.length === 0) return;
    const preferred = activeYears.find((year) => year.status === 'active') ?? activeYears[0];
    setValue('academic_year_id', preferred.id, { shouldValidate: true });
  }, [activeYears, initial, selectedYearId, setValue]);

  useEffect(() => {
    if (selectedClassId && !eligibleClasses.some((schoolClass) => schoolClass.id === selectedClassId)) {
      setValue('class_id', '', { shouldValidate: true });
    }
  }, [eligibleClasses, selectedClassId, setValue]);

  useEffect(() => {
    if (!selectedClass) return;
    setValue('cycle', selectedClass.education_system, { shouldValidate: true });
    setValue('speciality', selectedClass.speciality ?? '', { shouldValidate: true });
  }, [selectedClass, setValue]);

  const submit: SubmitHandler<StudentFormValues> = async (values) => {
    await onSubmit(cleanStudentPayload(values));
  };

  return (
    <form onSubmit={handleSubmit(submit)} className="space-y-6" noValidate>
      {/* ─── Identity ────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Personal information</CardTitle>
          <CardDescription>Identity and demographic details.</CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input
            label="Student matriculation number"
            placeholder="e.g. LAU-2026-001"
            hint="Enter the official number assigned by the school. It must be unique in this school."
            containerClassName="md:col-span-2"
            required
            error={errors.admission_number?.message}
            {...register('admission_number', {
              setValueAs: (value: string) => value.trim().toUpperCase(),
            })}
          />
          <Input label="First name"  required error={errors.first_name?.message}  {...register('first_name')} />
          <Input label="Last name"   required error={errors.last_name?.message}   {...register('last_name')} />
          <Input label="Middle name" error={errors.middle_name?.message}          {...register('middle_name')} />

          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">
              Gender <span className="text-danger">*</span>
            </label>
            <select
              {...register('gender')}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
            >
              {GENDER_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            {errors.gender?.message && <p className="text-sm text-danger">{errors.gender.message}</p>}
          </div>

          <Input type="date" label="Date of birth" required
            error={errors.date_of_birth?.message} {...register('date_of_birth')} />
          <Input label="Place of birth" error={errors.place_of_birth?.message} {...register('place_of_birth')} />
          <Input label="Nationality" error={errors.nationality?.message} {...register('nationality')} />
          <Input label="Religion" error={errors.religion?.message} {...register('religion')} />
        </CardContent>
      </Card>

      {!initial && !isTeacher && (
        <Card>
          <CardHeader>
            <CardTitle>School fees and physical payment</CardTitle>
            <CardDescription>
              Record cash received at registration. The system creates the invoice, calculates the balance, and makes the receipt available in Finance.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Input label="Fees configured by the principal (FCFA)" value={configuredFeeTotal > 0 ? configuredFeeTotal.toLocaleString() : 'Not configured'} disabled />
            <Input
              type="number"
              min={0}
              max={configuredFeeTotal > 0 ? configuredFeeTotal : undefined}
              step={500}
              label="Amount received (FCFA)"
              error={errors.initial_payment?.message}
              disabled={configuredFeeTotal <= 0}
              {...register('initial_payment', { valueAsNumber: true })}
            />
            <Input
              label="Remaining balance (FCFA)"
              value={Math.max(
                0,
                configuredFeeTotal - (Number(watch('initial_payment')) || 0),
              ).toLocaleString()}
              disabled
            />
            <div className="text-xs text-secondary-600 md:col-span-3">
              {configuredFeeTotal > 0 ? (
                <p>Payment method: Cash / physical payment. The amount above comes from the principal&apos;s fee structure for this year and class.</p>
              ) : isAdministrator ? (
                <p className="rounded-card border border-warning/40 bg-warning-light p-3">No required school fee is configured for this class. <Link href="/admin/finance/fees" className="font-semibold text-primary-700 underline">Configure school fees</Link> before registering the student.</p>
              ) : (
                <p className="rounded-card border border-warning/40 bg-warning-light p-3">The principal must configure the school fees for this class before registration can be completed.</p>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {/* ─── Enrollment ──────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Enrollment</CardTitle>
          <CardDescription>
            Academic year, class, cycle, and speciality (filière) information.
            The student's cycle and speciality determine their curriculum and report card structure.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Input type="date" label="Enrollment date" required
            error={errors.enrollment_date?.message} {...register('enrollment_date')} />

          <Input label="Previous school" error={errors.previous_school?.message} {...register('previous_school')} />

          {/* ── Cycle ───────────────────────────────────────────── */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">
              Cycle <span className="text-danger">*</span>
            </label>
            <select
              {...register('cycle')}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
            >
              {CYCLES.map((c) => (
                <option key={c.value} value={c.value}>{c.label_fr} ({c.label_en})</option>
              ))}
            </select>
            {errors.cycle?.message && (
              <p className="text-sm text-danger">{errors.cycle.message}</p>
            )}
          </div>

          <input type="hidden" {...register('speciality')} />
          <div className="rounded-card border border-primary-200 bg-primary-50 p-3 text-sm text-primary-900">
            General streams and technical specialities are defined by the selected class. Choose the education system first, then select the exact class below.
          </div>

          {/* Academic Year */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">
              Academic year <span className="text-danger">*</span>
            </label>
            <select
              {...register('academic_year_id')}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
            >
              <option value="">Select academic year...</option>
              {activeYears.map((y) => (
                <option key={y.id} value={y.id}>{y.title}</option>
              ))}
            </select>
            {errors.academic_year_id?.message && (
              <p className="text-sm text-danger">{errors.academic_year_id.message}</p>
            )}
          </div>

          {/* Class */}
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">
              Class <span className="text-danger">*</span>
            </label>
            <select
              {...register('class_id')}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
              disabled={!selectedYearId}
            >
              <option value="">
                {!selectedYearId
                  ? 'Select an academic year first'
                  : 'Select level, stream/speciality, and language section...'}
              </option>
              {eligibleClasses.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.grade_level} · {c.language === 'fr' ? 'French section' : 'English section'} · {c.speciality_label ?? c.name}
                </option>
              ))}
            </select>
            {errors.class_id?.message && (
              <p className="text-sm text-danger">{errors.class_id.message}</p>
            )}
            {selectedYearId && eligibleClasses.length === 0 && (
              <p className="text-sm text-warning">
                No {selectedCycle === 'secondary_general' ? 'general-secondary' : 'technical-secondary'} class exists for this year. The principal must create it under Academic → Classes first.
              </p>
            )}
          </div>
        </CardContent>
      </Card>

      {/* ─── Emergency ───────────────────────────────────────────── */}
      {!initial && (
        <Card>
          <CardHeader>
            <CardTitle>Parent or guardian</CardTitle>
            <CardDescription>Link this student to an existing parent, or create the parent and their portal account now.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-secondary-700">Parent registration <span className="text-danger">*</span></label>
              <select {...register('guardian_mode')} className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                <option value="new">Create a new parent or guardian</option>
                {!isTeacher && <option value="existing">Link an existing parent or guardian</option>}
              </select>
            </div>

            {guardianMode === 'existing' ? (
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-secondary-700">Existing parent <span className="text-danger">*</span></label>
                  <select {...register('existing_parent_id')} className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                    <option value="">Select parent or guardian...</option>
                    {(parentsData?.data ?? []).filter((parent) => parent.is_active).map((parent) => (
                      <option key={parent.id} value={parent.id}>{parent.full_name} · {parent.phone}</option>
                    ))}
                  </select>
                  {errors.existing_parent_id?.message && <p className="text-sm text-danger">{errors.existing_parent_id.message}</p>}
                </div>
                <Input label="Relationship" placeholder="e.g. Father, Mother, Guardian" required
                  error={errors.parent_relationship?.message} {...register('parent_relationship')} />
              </div>
            ) : (
              <>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <Input label="Parent first name" required error={errors.parent_first_name?.message} {...register('parent_first_name')} />
                  <Input label="Parent last name" required error={errors.parent_last_name?.message} {...register('parent_last_name')} />
                  <div className="flex flex-col gap-1.5">
                    <label className="text-sm font-medium text-secondary-700">Gender <span className="text-danger">*</span></label>
                    <select {...register('parent_gender')} className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                      <option value="female">Female</option>
                      <option value="male">Male</option>
                    </select>
                  </div>
                  <Input label="Relationship" placeholder="e.g. Father, Mother, Guardian" required
                    error={errors.parent_relationship?.message} {...register('parent_relationship')} />
                  <Input type="email" label="Parent email" required error={errors.parent_email?.message} {...register('parent_email')} />
                  <Input label="Parent phone" required placeholder="+237..." error={errors.parent_phone?.message} {...register('parent_phone')} />
                </div>
                <label className="flex items-center gap-2 text-sm text-secondary-700">
                  <input type="checkbox" {...register('create_parent_account')} className="rounded border-secondary-300 text-primary-600" />
                  Create parent portal login and generate temporary credentials
                </label>
              </>
            )}
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Emergency contact</CardTitle>
          <CardDescription>Who to call in case of emergency.</CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
          <Input label="Contact name" error={errors.emergency_contact_name?.message} {...register('emergency_contact_name')} />
          <Input label="Contact phone" error={errors.emergency_contact_phone?.message} {...register('emergency_contact_phone')} />
          <Input label="Relationship" placeholder="e.g. Father, Aunt, Guardian"
            error={errors.emergency_contact_relationship?.message} {...register('emergency_contact_relationship')} />
        </CardContent>
      </Card>

      {/* ─── Health ──────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Health (optional)</CardTitle>
          <CardDescription>Sensitive — visible only to administrators and school nurse.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Input label="Blood group" placeholder="e.g. O+" error={errors.blood_group?.message} {...register('blood_group')} />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-secondary-700">Allergies</label>
            <textarea rows={2} {...register('allergies')}
              className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-secondary-700">Medical conditions</label>
            <textarea rows={3} {...register('medical_conditions')}
              className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" />
          </div>
        </CardContent>
      </Card>

      <div className="flex items-center justify-end gap-3">
        {onCancel && <Button type="button" variant="outline" onClick={onCancel}>Cancel</Button>}
        <Button type="submit" isLoading={isSubmitting}>{submitLabel}</Button>
      </div>
    </form>
  );
}
