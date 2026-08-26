'use client';

import Link from 'next/link';
import { BarChart3, CalendarCheck, Users } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useTeacherDashboard } from '@/hooks/dashboard';

export default function TeacherClassesPage() {
  const { data, isLoading } = useTeacherDashboard();
  if (isLoading) return <><PageHeader title="My classes" /><Skeleton className="h-64" /></>;

  const classMap = new Map<string, {
    id: string;
    name: string;
    gradeLevel: string;
    speciality: string | null;
    cycle: string | null;
    language: 'fr' | 'en';
    academicYear: string | null;
    students?: number;
    formMaster: boolean;
    subjects: Array<{ name: string; coefficient: number; weeklyFrequency: number | null }>;
  }>();
  for (const schoolClass of data?.form_master_classes ?? []) {
    classMap.set(schoolClass.id, {
      id: schoolClass.id, name: schoolClass.name, students: schoolClass.students_count,
      gradeLevel: schoolClass.grade_level, speciality: schoolClass.speciality, cycle: schoolClass.cycle,
      language: schoolClass.language, academicYear: schoolClass.academic_year,
      formMaster: true, subjects: [],
    });
  }
  for (const assignment of data?.teaching ?? []) {
    const current = classMap.get(assignment.class_id) ?? {
      id: assignment.class_id, name: assignment.class_name,
      gradeLevel: assignment.grade_level, speciality: assignment.speciality, cycle: assignment.cycle,
      language: assignment.language, academicYear: assignment.academic_year,
      formMaster: false, subjects: [],
    };
    current.subjects.push({
      name: assignment.subject_name,
      coefficient: assignment.coefficient,
      weeklyFrequency: assignment.weekly_frequency,
    });
    classMap.set(assignment.class_id, current);
  }
  const classes = Array.from(classMap.values());

  return (
    <div>
      <PageHeader breadcrumb={[{ label: 'Home', href: '/teacher/dashboard' }, { label: 'My classes' }]}
        title="My classes" description="Classes where you teach a subject or serve as form master." />
      {classes.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">
          No class has been assigned to this teacher account yet.
        </CardContent></Card>
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {classes.map((schoolClass) => (
            <Card key={schoolClass.id}>
              <CardHeader><CardTitle className="text-base">{schoolClass.name}</CardTitle></CardHeader>
              <CardContent>
                <p className="mb-1 text-sm font-medium text-secondary-700">
                  {schoolClass.gradeLevel}{schoolClass.speciality ? ` · ${schoolClass.speciality}` : ''}
                </p>
                <p className="mb-3 text-sm text-secondary-500">
                  {schoolClass.formMaster ? 'Form master' : 'Subject teacher'}
                  {' · '}{schoolClass.cycle === 'first_cycle' ? 'First cycle' : schoolClass.cycle === 'second_cycle' ? 'Second cycle' : 'Cycle not set'}
                  {' · '}{schoolClass.language === 'fr' ? 'French programme' : 'English programme'}
                  {schoolClass.academicYear ? ` · ${schoolClass.academicYear}` : ''}
                  {schoolClass.students !== undefined && <> · <Users className="mx-1 inline h-3 w-3" />{schoolClass.students} students</>}
                </p>
                {schoolClass.subjects.length > 0 && (
                  <div className="mb-4 flex flex-wrap gap-1.5">
                    {schoolClass.subjects.map((subject) => (
                      <span key={subject.name} className="rounded-full bg-secondary-100 px-2 py-1 text-xs text-secondary-700">
                        {subject.name} · Coeff. {subject.coefficient}
                        {subject.weeklyFrequency !== null && ` · ${subject.weeklyFrequency}/week`}
                      </span>
                    ))}
                  </div>
                )}
                <div className="flex flex-wrap gap-2">
                  {schoolClass.subjects.length > 0 && (
                    <Link href={`/teacher/grades/${schoolClass.id}`}
                      className="inline-flex items-center gap-1 rounded-button bg-primary-600 px-3 py-2 text-sm font-medium text-white">
                      <BarChart3 className="h-4 w-4" /> Enter grades
                    </Link>
                  )}
                  {schoolClass.formMaster && (
                    <Link href={`/teacher/attendance/${schoolClass.id}?date=${new Date().toISOString().slice(0, 10)}`}
                      className="inline-flex items-center gap-1 rounded-button border border-secondary-300 px-3 py-2 text-sm font-medium text-secondary-700">
                      <CalendarCheck className="h-4 w-4" /> Attendance
                    </Link>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
