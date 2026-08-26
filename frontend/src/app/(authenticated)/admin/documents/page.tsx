'use client';

import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download, FileBadge2, Medal } from 'lucide-react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { useAcademicYears, useClasses, useTerms } from '@/hooks/academic';
import { teachersApi } from '@/lib/api/teachers';
import { studentsApi } from '@/lib/api/students';
import { apiClient } from '@/lib/api/client';
import type { Language } from '@/i18n/translations';
import { useTranslation } from '@/hooks/useTranslation';

interface HonorRow {
  class: string;
  student: { id: string; full_name: string; admission_number: string };
  average: number;
  rank: number;
  category: string;
}

async function downloadPdf(url: string, filename: string, params: Record<string, string | undefined>) {
  const response = await apiClient.get(url, { params, responseType: 'blob' });
  const objectUrl = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(objectUrl);
}

export default function DocumentsPage() {
  const { language: interfaceLanguage } = useTranslation();
  const [language, setLanguage] = useState<Language>(interfaceLanguage);
  const ui = (french: string, english: string) => interfaceLanguage === 'fr' ? french : english;
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((year) => year.status === 'active') ?? years?.[0], [years]);
  const [yearId, setYearId] = useState<string>();
  const effectiveYear = yearId ?? activeYear?.id;
  const { data: terms } = useTerms(effectiveYear);
  const [termId, setTermId] = useState<string>();
  const effectiveTerm = termId ?? terms?.find((term) => term.status === 'active')?.id ?? terms?.[0]?.id;
  const { data: classes } = useClasses({ academic_year_id: effectiveYear });
  const [classId, setClassId] = useState('');

  const { data: teachers } = useQuery({
    queryKey: ['teachers', 'certificate'],
    queryFn: () => teachersApi.list({ per_page: 200 }),
  });
  const { data: students } = useQuery({
    queryKey: ['students', 'certificate', classId],
    queryFn: () => studentsApi.list({ class_id: classId || undefined, per_page: 200 }),
  });
  const [teacherId, setTeacherId] = useState('');
  const [studentId, setStudentId] = useState('');

  useEffect(() => {
    setTermId(undefined);
  }, [effectiveYear]);

  const honor = useQuery({
    queryKey: ['honor-roll', effectiveYear, effectiveTerm, classId, language],
    enabled: Boolean(effectiveYear && effectiveTerm),
    queryFn: async () => {
      const response = await apiClient.get('/reports/honor-roll', {
        params: {
          academic_year_id: effectiveYear,
          term_id: effectiveTerm,
          class_id: classId || undefined,
          language,
        },
      });
      return response.data.data as { rows: HonorRow[] };
    },
  });

  const generateEmployment = async () => {
    if (!teacherId) return;
    try {
      await downloadPdf(
        `/reports/certificates/teachers/${teacherId}/employment`,
        'employment-certificate.pdf',
        { language },
      );
    } catch {
      toast.error(ui('Impossible de générer l’attestation de travail.', 'Could not generate the employment certificate.'));
    }
  };

  const generateSchool = async () => {
    if (!studentId || !effectiveYear) return;
    try {
      await downloadPdf(
        `/reports/certificates/students/${studentId}/school`,
        'school-certificate.pdf',
        { language, academic_year_id: effectiveYear },
      );
    } catch {
      toast.error(ui('Impossible de générer le certificat de scolarité.', 'Could not generate the school certificate.'));
    }
  };

  const downloadHonorRoll = async () => {
    if (!effectiveYear || !effectiveTerm) return;
    try {
      await downloadPdf('/reports/honor-roll/download', 'honor-roll.pdf', {
        academic_year_id: effectiveYear,
        term_id: effectiveTerm,
        class_id: classId || undefined,
        language,
      });
    } catch {
      toast.error(ui('Impossible de télécharger le tableau d’honneur.', 'Could not download the honor roll.'));
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: ui('Accueil', 'Home'), href: '/admin/dashboard' }, { label: ui('Documents officiels', 'Official documents') }]}
        title={ui('Documents officiels et tableau d’honneur', 'Official documents and honor roll')}
        description={ui('Générez des certificats bilingues et des rapports de distinction.', 'Generate bilingual certificates and performance recognition reports.')}
        actions={
          <select
            value={language}
            onChange={(event) => setLanguage(event.target.value as Language)}
            className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
          >
            <option value="fr">🇫🇷 Français</option>
            <option value="en">🇬🇧 English</option>
          </select>
        }
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{ui('Attestation de travail de l’enseignant', 'Teacher employment certificate')}</CardTitle>
            <CardDescription>{ui('Attestation officielle avec ancienneté calculée automatiquement.', 'Official certificate of employment/work with calculated years of service.')}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <select value={teacherId} onChange={(event) => setTeacherId(event.target.value)}
              className="h-10 w-full rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">{ui('Sélectionner un enseignant', 'Select a teacher')}</option>
              {(teachers?.data ?? []).map((teacher) => (
                <option key={teacher.id} value={teacher.id}>{teacher.full_name} · {teacher.employee_number}</option>
              ))}
            </select>
            <Button disabled={!teacherId} leftIcon={<FileBadge2 className="h-4 w-4" />} onClick={() => void generateEmployment()}>
              {ui('Générer le PDF', 'Generate PDF')}
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{ui('Certificat de scolarité', 'Student school certificate')}</CardTitle>
            <CardDescription>{ui('Confirmation d’inscription avec photo et année scolaire.', 'Enrollment confirmation with student photograph and academic year.')}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <select value={effectiveYear ?? ''} onChange={(event) => setYearId(event.target.value)}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                {(years ?? []).map((year) => <option key={year.id} value={year.id}>{year.title}</option>)}
              </select>
              <select value={classId} onChange={(event) => { setClassId(event.target.value); setStudentId(''); }}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                <option value="">{ui('Toutes les classes', 'All classes')}</option>
                {(classes ?? []).map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
              </select>
            </div>
            <select value={studentId} onChange={(event) => setStudentId(event.target.value)}
              className="h-10 w-full rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">{ui('Sélectionner un élève', 'Select a student')}</option>
              {(students?.data ?? []).map((student) => (
                <option key={student.id} value={student.id}>{student.full_name} · {student.admission_number}</option>
              ))}
            </select>
            <Button disabled={!studentId || !effectiveYear} leftIcon={<FileBadge2 className="h-4 w-4" />} onClick={() => void generateSchool()}>
              {ui('Générer le PDF', 'Generate PDF')}
            </Button>
          </CardContent>
        </Card>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>{ui('Tableau d’honneur', 'Honor roll')}</CardTitle>
          <CardDescription>{ui('Catégories de mérite automatiques par classe ou pour toute l’école.', 'Automatic performance categories by class or for the entire school.')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap gap-3">
            <select value={effectiveTerm ?? ''} onChange={(event) => setTermId(event.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              {(terms ?? []).map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
            </select>
            <select value={classId} onChange={(event) => setClassId(event.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">{ui('Toute l’école', 'Entire school')}</option>
              {(classes ?? []).map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name}</option>)}
            </select>
            <Button leftIcon={<Download className="h-4 w-4" />} onClick={() => void downloadHonorRoll()}>
              {ui('Télécharger le PDF', 'Download PDF')}
            </Button>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead><tr className="border-b text-left text-secondary-500"><th className="p-2">{ui('Élève', 'Student')}</th><th>{ui('Classe', 'Class')}</th><th>{ui('Moyenne', 'Average')}</th><th>{ui('Rang', 'Rank')}</th><th>{ui('Catégorie', 'Category')}</th></tr></thead>
              <tbody>
                {(honor.data?.rows ?? []).map((row) => (
                  <tr key={`${row.student.id}-${row.class}`} className="border-b border-secondary-100">
                    <td className="p-2 font-medium">{row.student.full_name}</td><td>{row.class}</td>
                    <td>{row.average.toFixed(2)}/20</td><td>{row.rank}</td>
                    <td><span className="inline-flex items-center gap-1"><Medal className="h-4 w-4 text-warning" />{row.category}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
