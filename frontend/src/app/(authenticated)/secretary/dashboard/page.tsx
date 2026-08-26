'use client';

import Link from 'next/link';
import { GraduationCap, AlertTriangle } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useSecretaryDashboard } from '@/hooks/dashboard';
import { useAuthStore } from '@/store/auth';
import { useTranslation } from '@/hooks/useTranslation';

export default function SecretaryDashboardPage() {
  const { data, isLoading } = useSecretaryDashboard();
  const user = useAuthStore((s) => s.user);
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  if (isLoading || !data) {
    return <><PageHeader title={`${ui('Bonjour', 'Hello')}${user ? `, ${user.first_name}` : ''}`} description={ui('Chargement…', 'Loading…')} /><Skeleton className="h-64" /></>;
  }

  return (
    <div>
      <PageHeader title={`${ui('Bonjour', 'Hello')}${user ? `, ${user.first_name}` : ''}`} description={ui('Votre aperçu opérationnel.', 'Your operational overview.')} />

      <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card><CardContent className="py-4"><p className="text-xs text-secondary-500">{ui('Inscriptions cette semaine', 'Enrolled this week')}</p><p className="text-2xl font-semibold text-ink">{data.enrollments.this_week}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-xs text-secondary-500">{ui('Inscriptions ce mois', 'Enrolled this month')}</p><p className="text-2xl font-semibold text-ink">{data.enrollments.this_month}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-xs text-secondary-500">{ui('Factures en attente', 'Pending invoices')}</p><p className="text-2xl font-semibold text-warning">{data.pending_invoices}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-xs text-secondary-500 flex items-center gap-1"><AlertTriangle className="h-3 w-3 text-danger" />{ui('Factures en retard', 'Overdue invoices')}</p><p className="text-2xl font-semibold text-danger">{data.overdue_invoices}</p></CardContent></Card>
      </div>

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="text-base">{ui('Élèves récemment inscrits', 'Recently enrolled students')}</CardTitle>
            <Link href="/secretary/students" className="text-xs text-primary-600 hover:underline">{ui('Tous les élèves', 'All students')} →</Link>
          </div>
        </CardHeader>
        <CardContent>
          {data.recent_students.length === 0 ? (
            <p className="py-4 text-center text-sm text-secondary-500">{ui('Aucune inscription récente.', 'No recent enrollments.')}</p>
          ) : (
            <ul className="divide-y divide-secondary-100">
              {data.recent_students.map((s) => (
                <li key={s.id} className="flex items-center justify-between py-2">
                  <div className="flex items-center gap-2">
                    <GraduationCap className="h-4 w-4 text-secondary-400" />
                    <div>
                      <Link href={`/secretary/students/${s.id}`} className="font-medium text-ink hover:text-primary-600">{s.full_name}</Link>
                      <p className="text-xs text-secondary-500">{s.admission_number}</p>
                    </div>
                  </div>
                  <span className="text-xs text-secondary-500">{s.created_at}</span>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
