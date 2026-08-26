'use client';

import Link from 'next/link';
import { Users, BookOpen, Calendar } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useTeacherDashboard } from '@/hooks/dashboard';
import { useTranslation } from '@/hooks/useTranslation';

export default function TeacherDashboardPage() {
  const { data, isLoading, isError } = useTeacherDashboard();
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  if (isLoading) {
    return <><PageHeader title={ui('Ma salle de classe', 'My classroom')} description={ui('Chargement…', 'Loading…')} /><Skeleton className="h-64" /></>;
  }
  if (isError) {
    return <><PageHeader title={ui('Ma salle de classe', 'My classroom')} /><Card><CardContent className="py-12 text-center text-sm text-danger">{ui('Le tableau de bord enseignant n’a pas pu être chargé. Actualisez la page après avoir vérifié le serveur.', 'The teacher dashboard could not be loaded. Refresh after checking the server.')}</CardContent></Card></>;
  }
  if (!data?.teacher) {
    return <><PageHeader title={ui('Ma salle de classe', 'My classroom')} /><Card><CardContent className="py-12 text-center text-sm text-warning-dark">{ui('Ce compte enseignant n’est lié à aucun dossier enseignant. Contactez un administrateur.', 'This teacher account is not linked to a teacher record. Contact an administrator.')}</CardContent></Card></>;
  }

  // Group teaching by class to keep the list compact.
  const byClass = new Map<string, {
    name: string;
    gradeLevel: string;
    speciality: string | null;
    cycle: string | null;
    classLanguage: 'fr' | 'en';
    academicYear: string | null;
    subjects: Array<{ id: string; name: string; color: string; coefficient: number; weeklyFrequency: number | null }>;
  }>();
  for (const t of data.teaching) {
    if (!byClass.has(t.class_id)) byClass.set(t.class_id, {
      name: t.class_name,
      gradeLevel: t.grade_level,
      speciality: t.speciality,
      cycle: t.cycle,
      classLanguage: t.language,
      academicYear: t.academic_year,
      subjects: [],
    });
    byClass.get(t.class_id)!.subjects.push({
      id: t.subject_id,
      name: t.subject_name,
      color: t.subject_color,
      coefficient: t.coefficient,
      weeklyFrequency: t.weekly_frequency,
    });
  }

  const classDetails = (gradeLevel: string, cycle: string | null, classLanguage: 'fr' | 'en', academicYear: string | null, speciality?: string | null) => [
    gradeLevel,
    speciality,
    cycle === 'first_cycle' ? ui('Premier cycle', 'First cycle') : cycle === 'second_cycle' ? ui('Second cycle', 'Second cycle') : null,
    classLanguage === 'fr' ? ui('Programme français', 'French programme') : ui('Programme anglais', 'English programme'),
    academicYear,
  ].filter(Boolean).join(' · ');

  return (
    <div>
      <PageHeader title={`${ui('Bienvenue', 'Welcome')}, ${data.teacher.full_name}`} description={`${ui('Matricule', 'Employee #')} ${data.teacher.employee_number}`} />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle className="text-base">{ui('Classes dont je suis titulaire', 'Classes I form-master')}</CardTitle></CardHeader>
          <CardContent>
            {data.form_master_classes.length === 0 ? (
              <p className="py-4 text-center text-sm text-secondary-500">{ui('Vous n’êtes titulaire d’aucune classe.', 'You are not the form master of any class.')}</p>
            ) : (
              <ul className="divide-y divide-secondary-100">
                {data.form_master_classes.map((c) => (
                  <li key={c.id} className="flex items-center justify-between py-2">
                    <div>
                      <p className="font-medium text-ink">{c.name}</p>
                      <p className="text-xs text-secondary-500">{classDetails(c.grade_level, c.cycle, c.language, c.academic_year, c.speciality)}</p>
                    </div>
                    <div className="flex items-center gap-3">
                      <span className="text-sm text-secondary-500"><Users className="mr-1 inline h-3 w-3" />{c.students_count}</span>
                      <Link href={`/teacher/attendance/${c.id}?date=${new Date().toISOString().slice(0,10)}`}
                        className="text-xs text-primary-600 hover:underline">
                        <Calendar className="mr-1 inline h-3 w-3" />{ui('Faire l’appel', 'Take attendance')}
                      </Link>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{ui('Matières que j’enseigne', 'Subjects I teach')}</CardTitle></CardHeader>
          <CardContent>
            {byClass.size === 0 ? (
              <p className="py-4 text-center text-sm text-secondary-500">{ui('Aucune affectation pour le moment.', 'No teaching assignments yet.')}</p>
            ) : (
              <ul className="space-y-3">
                {Array.from(byClass.entries()).map(([classId, info]) => (
                  <li key={classId}>
                    <p className="font-medium text-ink"><BookOpen className="mr-1 inline h-3 w-3 text-secondary-400" />{info.name}</p>
                    <p className="mt-0.5 text-xs text-secondary-500">
                      {classDetails(info.gradeLevel, info.cycle, info.classLanguage, info.academicYear, info.speciality)}
                    </p>
                    <div className="mt-1 flex flex-wrap gap-1">
                      {info.subjects.map((s) => (
                        <span key={s.id} className="inline-flex items-center gap-1 rounded-full bg-secondary-100 px-2 py-0.5 text-xs">
                          <span className="h-2 w-2 rounded-full" style={{ backgroundColor: s.color }} />
                          {s.name} · {ui('Coeff.', 'Coeff.')} {s.coefficient}
                          {s.weeklyFrequency !== null && ` · ${s.weeklyFrequency} ${ui('périodes/sem.', 'periods/week')}`}
                        </span>
                      ))}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
