'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { Pencil, Archive as ArchiveIcon, RotateCcw, ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { StudentStatusBadge } from '@/components/students/StudentStatusBadge';
import { StudentPhotoUpload } from '@/components/students/StudentPhotoUpload';
import { ArchiveStudentDialog, RestoreStudentDialog } from '@/components/students/StudentArchiveDialogs';
import { useStudent, useStudentAcademicHistory } from '@/hooks/students';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils/cn';

type Tab = 'personal' | 'contact' | 'family' | 'health' | 'history';

const TABS: { id: Tab; label: string }[] = [
  { id: 'personal', label: 'Personal' },
  { id: 'contact',  label: 'Contact' },
  { id: 'family',   label: 'Family & Enrollment' },
  { id: 'health',   label: 'Health' },
  { id: 'history',  label: 'History' },
];

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="grid grid-cols-3 gap-2 py-2">
      <dt className="text-sm font-medium text-secondary-600">{label}</dt>
      <dd className="col-span-2 text-sm text-ink">{value ?? <span className="text-secondary-400">—</span>}</dd>
    </div>
  );
}

export default function StudentProfilePage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const id = params.id;

  const { data: student, isLoading, isError } = useStudent(id);
  const { data: academicHistory = [], isLoading: isHistoryLoading } = useStudentAcademicHistory(id);
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const role = useAuthStore((state) => state.user?.role.name);
  const isSecretary = role === 'secretary';
  const studentsBase = isSecretary ? '/secretary/students' : '/admin/students';
  const classesBase = isSecretary ? '/secretary/classes' : '/admin/academic/classes';
  const parentsBase = isSecretary ? '/secretary/parents' : '/admin/parents';
  const dashboardBase = isSecretary ? '/secretary/dashboard' : '/admin/dashboard';
  const [tab, setTab] = useState<Tab>('personal');
  const [archiveOpen, setArchiveOpen] = useState(false);
  const [restoreOpen, setRestoreOpen] = useState(false);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-1/3" />
        <Skeleton className="h-32" />
        <Skeleton className="h-64" />
      </div>
    );
  }

  if (isError || !student) {
    return (
      <Card>
        <CardContent className="py-12 text-center">
          <h2 className="text-lg font-semibold text-ink">Student not found</h2>
          <p className="mt-2 text-sm text-secondary-500">The student you&apos;re looking for doesn&apos;t exist or has been deleted.</p>
          <Button className="mt-6" variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />}
            onClick={() => router.push(studentsBase)}>Back to students</Button>
        </CardContent>
      </Card>
    );
  }

  const isArchived = student.status === 'archived';

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Students', href: studentsBase },
          { label: student.full_name },
        ]}
        title={student.full_name}
        description={`Admission number: ${student.admission_number}`}
        actions={
          <>
            {hasPermission('student.edit') && !isArchived && (
              <Button variant="outline" leftIcon={<Pencil className="h-4 w-4" />}
                onClick={() => router.push(`${studentsBase}/${student.id}/edit`)}>
                Edit
              </Button>
            )}
            {!isArchived && hasPermission('student.archive') && (
              <Button variant="danger" leftIcon={<ArchiveIcon className="h-4 w-4" />}
                onClick={() => setArchiveOpen(true)}>
                Archive
              </Button>
            )}
            {isArchived && hasPermission('student.restore') && (
              <Button leftIcon={<RotateCcw className="h-4 w-4" />} onClick={() => setRestoreOpen(true)}>
                Restore
              </Button>
            )}
          </>
        }
      />

      {/* Header summary */}
      <Card className="mb-6">
        <CardContent className="flex flex-wrap items-center gap-6 py-6">
          <StudentPhotoUpload student={student} />
          <div className="flex-1">
            <div className="flex items-center gap-2">
              <h2 className="text-xl font-semibold text-ink">{student.full_name}</h2>
              <StudentStatusBadge status={student.status} />
            </div>
            <p className="text-sm text-secondary-500 capitalize">
              {student.gender} · {student.age} years old · Enrolled {student.enrollment_date}
            </p>
            {student.academic_year && (
              <p className="mt-1 text-xs text-secondary-500">
                Academic year: <span className="font-medium text-secondary-700">{student.academic_year.title}</span>
              </p>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Tabs */}
      <div className="mb-4 border-b border-secondary-200">
        <nav className="flex gap-1" role="tablist">
          {TABS.map((t) => (
            <button
              key={t.id}
              role="tab"
              aria-selected={tab === t.id}
              onClick={() => setTab(t.id)}
              className={cn(
                'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                tab === t.id
                  ? 'border-primary-600 text-primary-700'
                  : 'border-transparent text-secondary-500 hover:text-ink',
              )}
            >
              {t.label}
            </button>
          ))}
        </nav>
      </div>

      <Card>
        <CardContent className="py-6">
          {tab === 'personal' && (
            <dl className="divide-y divide-secondary-100">
              <Field label="First name"     value={student.first_name} />
              <Field label="Last name"      value={student.last_name} />
              <Field label="Middle name"    value={student.middle_name} />
              <Field label="Gender"         value={<span className="capitalize">{student.gender}</span>} />
              <Field label="Date of birth"  value={`${student.date_of_birth} (age ${student.age})`} />
              <Field label="Place of birth" value={student.place_of_birth} />
              <Field label="Nationality"    value={student.nationality} />
              <Field label="Religion"       value={student.religion} />
            </dl>
          )}

          {tab === 'contact' && (
            <dl className="divide-y divide-secondary-100">
              <Field label="Email"   value={student.email} />
              <Field label="Phone"   value={student.phone} />
              <Field label="Address" value={student.address} />
              <Field label="City"    value={student.city} />
              <Field label="Country" value={student.country} />
            </dl>
          )}

          {tab === 'family' && (
            <>
              <CardHeader className="-mx-6 -mt-6 mb-4 border-b border-secondary-200">
                <CardTitle className="text-base">Enrollment</CardTitle>
              </CardHeader>
              <dl className="divide-y divide-secondary-100">
                <Field label="Admission number" value={student.admission_number} />
                <Field label="Cycle"            value={student.cycle_label} />
                <Field label="Speciality (Filière)" value={student.speciality_label ?? <span className="text-secondary-400">Not applicable — general education</span>} />
                <Field label="Academic year"    value={student.academic_year?.title} />
                <Field label="Class" value={student.class ? (
                  <button
                    type="button"
                    onClick={() => router.push(`${classesBase}/${student.class?.id}`)}
                    className="font-medium text-primary-700 hover:underline"
                  >
                    {student.class.name}
                  </button>
                ) : <span className="text-secondary-400">No class assigned</span>} />
                <Field label="Enrollment date"  value={student.enrollment_date} />
                <Field label="Previous school"  value={student.previous_school} />
                <Field label="Parent / guardian" value={student.primary_parent ? (
                  <button
                    type="button"
                    onClick={() => router.push(`${parentsBase}/${student.primary_parent?.id}`)}
                    className="text-left font-medium text-primary-700 hover:underline"
                  >
                    {student.primary_parent.full_name} · {student.primary_parent.phone}
                    {student.primary_parent.has_portal ? ' · Portal active' : ''}
                  </button>
                ) : <span className="text-secondary-400">No parent linked</span>} />
              </dl>

              <h3 className="mt-8 mb-2 text-sm font-semibold uppercase tracking-wider text-secondary-500">Emergency contact</h3>
              <dl className="divide-y divide-secondary-100">
                <Field label="Name"         value={student.emergency_contact.name} />
                <Field label="Phone"        value={student.emergency_contact.phone} />
                <Field label="Relationship" value={student.emergency_contact.relationship} />
              </dl>
            </>
          )}

          {tab === 'health' && (
            <dl className="divide-y divide-secondary-100">
              <Field label="Blood group"       value={student.health.blood_group} />
              <Field label="Allergies"         value={student.health.allergies} />
              <Field label="Medical conditions" value={student.health.medical_conditions} />
            </dl>
          )}

          {tab === 'history' && (
            <div className="space-y-6">
              <div>
                <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-secondary-500">Academic history</h3>
                {isHistoryLoading ? (
                  <Skeleton className="h-24 w-full" />
                ) : academicHistory.length === 0 ? (
                  <p className="text-sm text-secondary-500">No academic enrollment history is available.</p>
                ) : (
                  <div className="overflow-x-auto rounded-lg border border-secondary-200">
                    <table className="min-w-full divide-y divide-secondary-200 text-sm">
                      <thead className="bg-secondary-50 text-left text-secondary-600">
                        <tr><th className="px-4 py-3">School year</th><th className="px-4 py-3">Class</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Average</th><th className="px-4 py-3">Decision</th></tr>
                      </thead>
                      <tbody className="divide-y divide-secondary-100">
                        {academicHistory.map((entry) => (
                          <tr key={entry.id}>
                            <td className="px-4 py-3 font-medium text-ink">{entry.academic_year.title}</td>
                            <td className="px-4 py-3">{entry.class?.name ?? '—'}</td>
                            <td className="px-4 py-3 capitalize">{entry.status}</td>
                            <td className="px-4 py-3">{entry.decision?.final_average ?? '—'}</td>
                            <td className="px-4 py-3">{entry.decision?.label ?? 'Pending'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
              <dl className="divide-y divide-secondary-100">
                <Field label="Created"     value={student.created_at ? new Date(student.created_at).toLocaleString() : null} />
                <Field label="Created by"  value={student.created_by?.full_name} />
                <Field label="Last update" value={student.updated_at ? new Date(student.updated_at).toLocaleString() : null} />
                <Field label="Archived at" value={student.archived_at ? new Date(student.archived_at).toLocaleString() : null} />
              </dl>
            </div>
          )}
        </CardContent>
      </Card>

      <ArchiveStudentDialog student={student} open={archiveOpen} onClose={() => setArchiveOpen(false)} />
      <RestoreStudentDialog student={student} open={restoreOpen} onClose={() => setRestoreOpen(false)} />
    </div>
  );
}
