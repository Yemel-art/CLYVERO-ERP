'use client';

import { useMemo, useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useParentDashboard } from '@/hooks/dashboard';
import { useAcademicYears, useTerms } from '@/hooks/academic';
import { useTermReport } from '@/hooks/grades';

export default function ParentGradesPage() {
  const { data: family } = useParentDashboard();
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((year) => year.status === 'active') ?? years?.[0], [years]);
  const { data: terms } = useTerms(activeYear?.id);
  const [studentId, setStudentId] = useState<string>();
  const [termId, setTermId] = useState<string>();
  const selectedStudent = studentId ?? family?.children[0]?.id;
  const selectedTerm = termId ?? terms?.find((term) => term.status === 'active')?.id ?? terms?.[0]?.id;
  const { data: report, isLoading } = useTermReport(selectedStudent, selectedTerm);

  return (
    <div>
      <PageHeader breadcrumb={[{ label: 'Home', href: '/parent/dashboard' }, { label: 'Grades' }]}
        title="My children’s grades" description="Published results for the selected term." />
      <Card className="mb-5"><CardContent className="flex flex-wrap gap-3 py-4">
        <select value={selectedStudent ?? ''} onChange={(event) => setStudentId(event.target.value)}
          className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
          {(family?.children ?? []).map((child) => <option key={child.id} value={child.id}>{child.full_name}</option>)}
        </select>
        <select value={selectedTerm ?? ''} onChange={(event) => setTermId(event.target.value)}
          className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
          {(terms ?? []).map((term) => <option key={term.id} value={term.id}>{term.name}</option>)}
        </select>
      </CardContent></Card>
      {isLoading ? <Skeleton className="h-48" /> : !report ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">No published results are available.</CardContent></Card>
      ) : (
        <Card><CardContent className="py-4">
          <div className="mb-4 flex items-center justify-between">
            <p className="font-semibold text-ink">{report.student.full_name} · {report.term.name}</p>
            <p className="text-lg font-bold text-primary-700">{report.report.overall_average ?? '—'}/20</p>
          </div>
          <div className="overflow-x-auto"><table className="w-full min-w-[32rem] text-sm">
            <thead><tr className="border-b text-left text-secondary-500"><th className="py-2">Subject</th><th>Coefficient</th><th>Average</th></tr></thead>
            <tbody>{report.report.subjects.map((subject) => (
              <tr key={subject.subject_id} className="border-b border-secondary-100">
                <td className="py-3 font-medium">{subject.name}</td><td>{subject.coefficient}</td><td>{subject.average ?? '—'}/20</td>
              </tr>
            ))}</tbody>
          </table></div>
        </CardContent></Card>
      )}
    </div>
  );
}
