'use client';

import { useState } from 'react';
import { useRouter, useParams } from 'next/navigation';
import { Pencil, Archive as ArchiveIcon, RotateCcw, ArrowLeft, CreditCard, Download } from 'lucide-react';
import { toast } from 'sonner';
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
import { studentsApi } from '@/lib/api/students';

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
  const [isDownloadingIdCard, setIsDownloadingIdCard] = useState(false);

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
  const cardLanguage = student.class?.language === 'fr' ? 'fr' : 'en';

  const downloadIdCard = async () => {
    setIsDownloadingIdCard(true);
    try {
      const blob = await studentsApi.downloadIdCard(student.id);
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `student-id-card-${student.admission_number}.pdf`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      URL.revokeObjectURL(url);
      toast.success(cardLanguage === 'fr' ? 'Carte scolaire téléchargée.' : 'Student ID card downloaded.');
    } catch {
      toast.error(cardLanguage === 'fr'
        ? 'Impossible de générer la carte. Vérifiez la classe, l’année scolaire et la photo.'
        : 'Unable to generate the card. Check the class, academic year, and photo.');
    } finally {
      setIsDownloadingIdCard(false);
    }
  };

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

      {/* Student identity card */}
      <Card className="mb-6 overflow-hidden">
        <CardHeader className="border-b border-secondary-200 bg-secondary-50">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <CreditCard className="h-5 w-5 text-primary-600" />
                {cardLanguage === 'fr' ? 'Carte d’identité scolaire' : 'Student Identity Card'}
              </CardTitle>
              <p className="mt-1 text-xs text-secondary-500">
                {cardLanguage === 'fr'
                  ? 'La langue est déterminée automatiquement par la section de la classe.'
                  : 'The language is selected automatically from the class section.'}
              </p>
            </div>
            {hasPermission('report_card.generate') && !isArchived && (
              <Button
                leftIcon={<Download className="h-4 w-4" />}
                isLoading={isDownloadingIdCard}
                onClick={() => { void downloadIdCard(); }}
              >
                {cardLanguage === 'fr' ? 'Exporter en PDF' : 'Export PDF'}
              </Button>
            )}
          </div>
        </CardHeader>
        <CardContent className="py-5">
          <div className="relative mx-auto grid aspect-[1.586/1] w-full max-w-[680px] grid-cols-[112px_minmax(0,1fr)] items-start gap-5 overflow-hidden rounded-2xl border-[6px] border-primary-600 bg-white p-4 pt-16 shadow-xl">
            <div className="absolute -right-12 -top-20 h-48 w-48 rotate-45 bg-primary-100" />
            <div className="absolute inset-x-2 top-2 border-b-2 border-primary-600 pb-2 text-center">
              <p className="text-[10px] font-bold uppercase tracking-wide text-secondary-600">{cardLanguage === 'fr' ? 'République du Cameroun · Paix – Travail – Patrie' : 'Republic of Cameroon · Peace – Work – Fatherland'}</p>
              <p className="text-sm font-black uppercase tracking-wide text-primary-700">{cardLanguage === 'fr' ? 'Carte d’identité scolaire' : 'Student Identity Card'}</p>
            </div>
            {student.photo_url ? (
              <img src={student.photo_url} alt="" className="h-32 w-24 border-2 border-primary-500 object-cover p-1" />
            ) : (
              <div className="flex h-32 w-24 items-center justify-center border-2 border-primary-500 bg-secondary-100 text-2xl font-black text-primary-600">
                {student.initials}
              </div>
            )}
            <dl className="relative grid min-w-0 grid-cols-[108px_minmax(0,1fr)] items-start gap-x-3 gap-y-1 text-xs sm:text-sm [&>dd]:min-w-0 [&>dd]:font-semibold [&>dt]:whitespace-nowrap [&>dt]:text-secondary-500">
              <dt>{cardLanguage === 'fr' ? 'Nom :' : 'Last name:'}</dt><dd className="truncate uppercase">{student.last_name}</dd>
              <dt>{cardLanguage === 'fr' ? 'Prénom(s) :' : 'First name(s):'}</dt><dd className="truncate uppercase">{[student.first_name, student.middle_name].filter(Boolean).join(' ')}</dd>
              <dt>{cardLanguage === 'fr' ? 'Né(e) le :' : 'Born on:'}</dt><dd>{student.date_of_birth ?? '—'}</dd>
              <dt>{cardLanguage === 'fr' ? 'À :' : 'At:'}</dt><dd className="truncate uppercase">{student.place_of_birth ?? '—'}</dd>
              <dt>{cardLanguage === 'fr' ? 'Sexe / Âge :' : 'Gender / Age:'}</dt><dd>{student.gender === 'female' ? 'F' : student.gender === 'male' ? 'M' : '—'} / {student.age ?? '—'}</dd>
              <dt className="mt-1 border-l-4 border-primary-600 bg-secondary-100 px-2 py-1">{cardLanguage === 'fr' ? 'Classe :' : 'Class:'}</dt><dd className="mt-1 line-clamp-2 min-h-8 bg-secondary-100 px-2 py-1 text-primary-700">{student.class?.identity_label ?? '—'}</dd>
              <dt>{cardLanguage === 'fr' ? 'Matricule :' : 'Student ID:'}</dt><dd className="truncate">{student.official_matricule ?? student.admission_number}</dd>
              <dt>{cardLanguage === 'fr' ? 'Année scolaire :' : 'Academic year:'}</dt><dd>{student.academic_year?.title ?? '—'}</dd>
            </dl>
          </div>
          <p className="mt-3 text-center text-xs text-secondary-500">
            {cardLanguage === 'fr'
              ? 'Le PDF utilise automatiquement l’en-tête officiel enregistré dans les paramètres de l’école.'
              : 'The PDF automatically uses the official header saved in the school settings.'}
          </p>
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
