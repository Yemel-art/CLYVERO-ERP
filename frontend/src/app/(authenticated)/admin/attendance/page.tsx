'use client';

import { useState, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { Calendar, Users, ChevronRight } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Input } from '@/components/ui/Input';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAcademicYears, useClasses } from '@/hooks/academic';
import { useTranslation } from '@/hooks/useTranslation';
import { useAuthStore } from '@/store/auth';
import { dashboardRouteFor } from '@/types/user';

export default function AttendanceHubPage() {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const dashboardHref = user ? dashboardRouteFor(user.role.name) : '/login';
  const attendanceBase = user?.role.name === 'teacher'
    ? '/teacher/attendance'
    : user?.role.name === 'secretary'
      ? '/secretary/attendance'
      : '/admin/attendance';
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((y) => y.status === 'active'), [years]);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const { data: classes, isLoading } = useClasses({ academic_year_id: activeYear?.id });

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: ui('Accueil', 'Home'), href: dashboardHref }, { label: ui('Présences', 'Attendance') }]}
        title={ui('Présences', 'Attendance')}
        description={activeYear
          ? (language === 'fr' ? `Enregistrez les présences pour une classe de l’année ${activeYear.title}.` : `Take attendance for any class in ${activeYear.title}.`)
          : ui('Créez d’abord une année scolaire pour enregistrer les présences.', 'Create an academic year first to start taking attendance.')}
      />

      <Card className="mb-6">
        <CardContent className="flex flex-wrap items-center gap-4 py-4">
          <Calendar className="h-5 w-5 text-secondary-500" />
          <div className="flex flex-1 items-center gap-3">
            <label className="text-sm font-medium text-secondary-700">{ui('Date', 'Date')}</label>
            <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="max-w-xs" />
          </div>
        </CardContent>
      </Card>

      {isLoading ? (
        <Skeleton className="h-48" />
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(classes ?? []).filter((c) => c.is_active).map((c) => (
            <Card key={c.id} className="cursor-pointer transition-shadow hover:shadow-card"
              onClick={() => router.push(`${attendanceBase}/${c.id}?date=${date}`)}>
              <CardContent className="py-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-semibold text-ink">{c.name}</p>
                    <p className="text-xs text-secondary-500">{c.grade_level}</p>
                    {c.form_master && <p className="mt-1 text-xs text-secondary-500">{c.form_master.full_name}</p>}
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="text-right">
                      <p className="flex items-center gap-1 text-sm text-secondary-500"><Users className="h-3 w-3" />{c.students_count ?? 0}</p>
                    </div>
                    <ChevronRight className="h-4 w-4 text-secondary-400" />
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
          {(classes ?? []).length === 0 && (
            <Card className="md:col-span-2 lg:col-span-3"><CardContent className="py-12 text-center text-sm text-secondary-500">{ui('Aucune classe configurée.', 'No classes set up yet.')}</CardContent></Card>
          )}
        </div>
      )}
    </div>
  );
}
