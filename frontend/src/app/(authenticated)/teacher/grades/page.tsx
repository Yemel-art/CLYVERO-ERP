'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import { ArrowRight, BarChart3 } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useTeacherDashboard } from '@/hooks/dashboard';
import { useAcademicYears, useTerms } from '@/hooks/academic';

export default function TeacherGradesPage() {
  const { data, isLoading } = useTeacherDashboard();
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((year) => year.status === 'active') ?? years?.[0], [years]);
  const { data: terms } = useTerms(activeYear?.id);
  const activeTerm = useMemo(() => terms?.find((term) => term.status === 'active') ?? terms?.[0], [terms]);
  const [termId, setTermId] = useState<string>();
  const effectiveTermId = termId ?? activeTerm?.id;

  const classes = useMemo(() => {
    const grouped = new Map<string, {
      id: string;
      name: string;
      gradeLevel: string;
      speciality: string | null;
      cycle: string | null;
      language: 'fr' | 'en';
      academicYear: string | null;
      subjects: Array<{ name: string; coefficient: number }>;
    }>();
    for (const assignment of data?.teaching ?? []) {
      const current = grouped.get(assignment.class_id) ?? {
        id: assignment.class_id,
        name: assignment.class_name,
        gradeLevel: assignment.grade_level,
        speciality: assignment.speciality,
        cycle: assignment.cycle,
        language: assignment.language,
        academicYear: assignment.academic_year,
        subjects: [],
      };
      current.subjects.push({ name: assignment.subject_name, coefficient: assignment.coefficient });
      grouped.set(assignment.class_id, current);
    }
    return Array.from(grouped.values());
  }, [data]);

  return (
    <div>
      <PageHeader breadcrumb={[{ label: 'Home', href: '/teacher/dashboard' }, { label: 'Grades' }]}
        title="My gradebooks"
        description="Choose one of your assigned classes to create assessments and enter student grades." />
      <Card className="mb-6">
        <CardContent className="flex items-center gap-4 py-4">
          <BarChart3 className="h-5 w-5 text-secondary-500" />
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">Term</label>
            <select value={effectiveTermId ?? ''} onChange={(event) => setTermId(event.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              {(terms ?? []).map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
            </select>
          </div>
        </CardContent>
      </Card>
      {isLoading ? <Skeleton className="h-48" /> : classes.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">
          No subject has been assigned to you yet. An administrator must assign you to a subject under Academic → Classes.
        </CardContent></Card>
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          {classes.map((schoolClass) => (
            <Link key={schoolClass.id}
              href={`/teacher/grades/${schoolClass.id}${effectiveTermId ? `?term=${effectiveTermId}` : ''}`}
              className="rounded-card border border-secondary-200 bg-surface p-5 shadow-card transition-shadow hover:shadow-lg">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-semibold text-ink">{schoolClass.name}</p>
                  <p className="mt-1 text-xs text-secondary-500">
                    {schoolClass.gradeLevel}{schoolClass.speciality ? ` · ${schoolClass.speciality}` : ''}
                    {' · '}{schoolClass.cycle === 'first_cycle' ? 'First cycle' : schoolClass.cycle === 'second_cycle' ? 'Second cycle' : 'Cycle not set'}
                    {' · '}{schoolClass.language === 'fr' ? 'French programme' : 'English programme'}
                    {schoolClass.academicYear ? ` · ${schoolClass.academicYear}` : ''}
                  </p>
                  <p className="mt-2 text-sm text-secondary-600">
                    {schoolClass.subjects.map((subject) => `${subject.name} (Coeff. ${subject.coefficient})`).join(', ')}
                  </p>
                </div>
                <ArrowRight className="h-4 w-4 text-secondary-400" />
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
