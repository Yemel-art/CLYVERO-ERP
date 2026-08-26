'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { Pencil, Archive as ArchiveIcon, RotateCcw, ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
import { TeacherStatusBadge } from '@/components/teachers/TeacherStatusBadge';
import { ArchiveTeacherDialog, RestoreTeacherDialog } from '@/components/teachers/TeacherArchiveDialogs';
import { useTeacher } from '@/hooks/teachers';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils/cn';
import { TeacherPhotoUpload } from '@/components/teachers/TeacherPhotoUpload';

type Tab = 'personal' | 'employment' | 'assignments' | 'contact' | 'history';

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="grid grid-cols-3 gap-2 py-2">
      <dt className="text-sm font-medium text-secondary-600">{label}</dt>
      <dd className="col-span-2 text-sm text-ink">{value ?? <span className="text-secondary-400">—</span>}</dd>
    </div>
  );
}

export default function TeacherProfilePage() {
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const { data: teacher, isLoading, isError } = useTeacher(id);
  const role = useAuthStore((s) => s.user?.role.name);
  const hasPermission = useAuthStore((s) => s.hasPermission);
  const teachersBase = role === 'secretary' ? '/secretary/teachers' : '/admin/teachers';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const [tab, setTab] = useState<Tab>('personal');
  const [archiveOpen, setArchiveOpen] = useState(false);
  const [restoreOpen, setRestoreOpen] = useState(false);

  if (isLoading) {
    return <div className="space-y-4"><Skeleton className="h-8 w-1/3" /><Skeleton className="h-32" /><Skeleton className="h-64" /></div>;
  }
  if (isError || !teacher) {
    return (
      <Card><CardContent className="py-12 text-center">
        <h2 className="text-lg font-semibold text-ink">Teacher not found</h2>
        <Button className="mt-6" variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />}
          onClick={() => router.push(teachersBase)}>Back to teachers</Button>
      </CardContent></Card>
    );
  }

  const isArchived = teacher.status === 'archived';
  const tabs: { id: Tab; label: string }[] = [
    { id: 'personal',   label: 'Personal' },
    { id: 'employment', label: 'Employment' },
    { id: 'assignments', label: 'Classes & subjects' },
    { id: 'contact',    label: 'Contact' },
    { id: 'history',    label: 'History' },
  ];

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Teachers', href: teachersBase },
          { label: teacher.full_name },
        ]}
        title={teacher.full_name}
        description={`Employee number: ${teacher.employee_number}`}
        actions={<>
          {hasPermission('teacher.edit') && !isArchived && (
            <Button variant="outline" leftIcon={<Pencil className="h-4 w-4" />}
              onClick={() => router.push(`${teachersBase}/${teacher.id}/edit`)}>Edit</Button>
          )}
          {!isArchived && hasPermission('teacher.delete') && (
            <Button variant="danger" leftIcon={<ArchiveIcon className="h-4 w-4" />} onClick={() => setArchiveOpen(true)}>Archive</Button>
          )}
          {isArchived && hasPermission('teacher.delete') && (
            <Button leftIcon={<RotateCcw className="h-4 w-4" />} onClick={() => setRestoreOpen(true)}>Restore</Button>
          )}
        </>}
      />

      <Card className="mb-6">
        <CardContent className="flex items-center gap-6 py-6">
          {hasPermission('teacher.edit')
            ? <TeacherPhotoUpload teacher={teacher} />
            : teacher.photo_url
              ? <img src={teacher.photo_url} alt="" className="h-24 w-24 rounded-full object-cover ring-2 ring-secondary-100" />
              : <Avatar name={teacher.full_name} size="lg" className="h-24 w-24 text-2xl" />}
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-xl font-semibold text-ink">{teacher.full_name}</h2>
              <TeacherStatusBadge status={teacher.status} />
            </div>
            <p className="text-sm text-secondary-500 capitalize">{teacher.gender} · {teacher.qualification ?? 'No qualification recorded'}</p>
            <p className="mt-1 text-xs text-secondary-500">Hired {teacher.hire_date} · {teacher.years_of_experience} years experience</p>
          </div>
        </CardContent>
      </Card>

      <div className="mb-4 border-b border-secondary-200">
        <nav className="flex gap-1" role="tablist">
          {tabs.map((t) => (
            <button key={t.id} role="tab" aria-selected={tab === t.id} onClick={() => setTab(t.id)}
              className={cn('px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                tab === t.id ? 'border-primary-600 text-primary-700' : 'border-transparent text-secondary-500 hover:text-ink')}>
              {t.label}
            </button>
          ))}
        </nav>
      </div>

      <Card><CardContent className="py-6">
        {tab === 'personal' && (
          <dl className="divide-y divide-secondary-100">
            <Field label="First name"    value={teacher.first_name} />
            <Field label="Last name"     value={teacher.last_name} />
            <Field label="Middle name"   value={teacher.middle_name} />
            <Field label="Gender"        value={<span className="capitalize">{teacher.gender}</span>} />
            <Field label="Date of birth" value={teacher.date_of_birth} />
            <Field label="Nationality"   value={teacher.nationality} />
          </dl>
        )}
        {tab === 'employment' && (
          <dl className="divide-y divide-secondary-100">
            <Field label="Employee number" value={teacher.employee_number} />
            <Field label="Qualification"   value={teacher.qualification} />
            <Field label="Specialization"  value={teacher.specialization} />
            <Field label="Experience"      value={`${teacher.years_of_experience} years`} />
            <Field label="Hire date"       value={teacher.hire_date} />
            {teacher.salary !== undefined && (
              <Field label="Salary (XAF)" value={teacher.salary !== null
                ? new Intl.NumberFormat('fr-CM').format(teacher.salary)
                : null} />
            )}
            <Field label="Status" value={<TeacherStatusBadge status={teacher.status} />} />
          </dl>
        )}
        {tab === 'assignments' && (
          <div className="space-y-6">
            <div>
              <h3 className="text-sm font-semibold text-ink">Teaching assignments</h3>
              {(teacher.teaching_assignments ?? []).length === 0 ? (
                <p className="mt-3 rounded-card bg-secondary-50 p-4 text-sm text-secondary-500">
                  No class subject is assigned yet. Open Academic structure → Classes, select a class, then choose this teacher beside the subject.
                </p>
              ) : (
                <div className="mt-3 overflow-hidden rounded-card border border-secondary-200">
                  {(teacher.teaching_assignments ?? []).map((assignment) => (
                    <div key={`${assignment.class_id}-${assignment.subject_id}`} className="flex items-center justify-between border-b border-secondary-100 px-4 py-3 last:border-0">
                      <div>
                        <p className="font-medium text-ink">{assignment.class_name}</p>
                        <p className="text-xs text-secondary-500">
                          {assignment.grade_level}{assignment.speciality ? ` · ${assignment.speciality}` : ''}
                          {' · '}{assignment.cycle === 'first_cycle' ? 'First cycle' : assignment.cycle === 'second_cycle' ? 'Second cycle' : 'Cycle not set'}
                          {' · '}{assignment.language === 'fr' ? 'French programme' : 'English programme'}
                        </p>
                        <p className="mt-1 text-sm text-secondary-600">
                          {assignment.subject_name} · Coeff. {assignment.coefficient}
                          {assignment.weekly_frequency !== null && ` · ${assignment.weekly_frequency} periods/week`}
                        </p>
                      </div>
                      <span className="text-xs text-secondary-500">{assignment.academic_year ?? 'Current year'}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div>
              <h3 className="text-sm font-semibold text-ink">Form-master classes</h3>
              {(teacher.form_master_classes ?? []).length === 0 ? (
                <p className="mt-2 text-sm text-secondary-500">Not assigned as form master.</p>
              ) : (
                <div className="mt-2 space-y-2">
                  {(teacher.form_master_classes ?? []).map((schoolClass) => (
                    <p key={schoolClass.id} className="text-sm text-secondary-700">
                      <span className="font-medium text-ink">{schoolClass.name}</span>
                      {' · '}{schoolClass.grade_level}{schoolClass.speciality ? ` · ${schoolClass.speciality}` : ''}
                      {' · '}{schoolClass.language === 'fr' ? 'French programme' : 'English programme'}
                      {schoolClass.academic_year ? ` · ${schoolClass.academic_year}` : ''}
                    </p>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}
        {tab === 'contact' && (
          <dl className="divide-y divide-secondary-100">
            <Field label="Email"   value={teacher.email} />
            <Field label="Phone"   value={teacher.phone} />
            <Field label="Address" value={teacher.address} />
            <Field label="City"    value={teacher.city} />
            <Field label="Country" value={teacher.country} />
            <Field label="Emergency contact" value={teacher.emergency_contact.name} />
            <Field label="Emergency phone"   value={teacher.emergency_contact.phone} />
          </dl>
        )}
        {tab === 'history' && (
          <dl className="divide-y divide-secondary-100">
            <Field label="Created at"  value={teacher.created_at ? new Date(teacher.created_at).toLocaleString() : null} />
            <Field label="Archived at" value={teacher.archived_at ? new Date(teacher.archived_at).toLocaleString() : null} />
            <Field label="Last login"  value={teacher.user?.last_login_at ? new Date(teacher.user.last_login_at).toLocaleString() : null} />
            <Field label="Login active" value={teacher.user ? (teacher.user.is_active ? 'Yes' : 'No') : <span className="text-secondary-400">No login account</span>} />
          </dl>
        )}
      </CardContent></Card>

      <ArchiveTeacherDialog teacher={teacher} open={archiveOpen} onClose={() => setArchiveOpen(false)} />
      <RestoreTeacherDialog teacher={teacher} open={restoreOpen} onClose={() => setRestoreOpen(false)} />
    </div>
  );
}
