'use client';

import { useState } from 'react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useParentDashboard } from '@/hooks/dashboard';
import { useStudentAttendanceSummary } from '@/hooks/attendance';

export default function ParentAttendancePage() {
  const { data: family } = useParentDashboard();
  const [studentId, setStudentId] = useState<string>();
  const selectedStudent = studentId ?? family?.children[0]?.id;
  const { data, isLoading } = useStudentAttendanceSummary(selectedStudent);
  const stats = [
    ['Present', data?.present ?? 0], ['Absent', data?.absent ?? 0],
    ['Late', data?.late ?? 0], ['Excused', data?.excused ?? 0],
  ];
  return <div>
    <PageHeader breadcrumb={[{ label: 'Home', href: '/parent/dashboard' }, { label: 'Attendance' }]}
      title="Attendance" description="Attendance summary for your children." />
    <select value={selectedStudent ?? ''} onChange={(event) => setStudentId(event.target.value)}
      className="mb-5 h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
      {(family?.children ?? []).map((child) => <option key={child.id} value={child.id}>{child.full_name}</option>)}
    </select>
    {isLoading ? <Skeleton className="h-32" /> : <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
      {stats.map(([label, value]) => <Card key={label}><CardContent className="py-4"><p className="text-sm text-secondary-500">{label}</p><p className="text-2xl font-semibold">{value}</p></CardContent></Card>)}
      <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">Attendance rate</p><p className="text-2xl font-semibold">{data?.rate ?? 0}%</p></CardContent></Card>
    </div>}
  </div>;
}
