'use client';

import Link from 'next/link';
import { Users, GraduationCap, UserCheck, BookOpen, TrendingUp, Receipt, AlertTriangle } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAdminDashboard } from '@/hooks/dashboard';
import { useAuthStore } from '@/store/auth';
import { useTranslation } from '@/hooks/useTranslation';

function formatXAF(n: number) {
  return new Intl.NumberFormat('fr-CM').format(Math.round(n)) + ' XAF';
}

export default function AdminDashboardPage() {
  const { data, isLoading } = useAdminDashboard();
  const user = useAuthStore((s) => s.user);
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;

  if (isLoading || !data) {
    return (
      <div>
        <PageHeader title={`${ui('Bienvenue', 'Welcome')}${user ? `, ${user.first_name}` : ''}`} description={ui('Chargement de la vue d’ensemble…', 'Loading overview…')} />
        <div className="grid grid-cols-1 gap-4 md:grid-cols-4"><Skeleton className="h-24" /><Skeleton className="h-24" /><Skeleton className="h-24" /><Skeleton className="h-24" /></div>
      </div>
    );
  }

  const tiles = [
    { label: ui('Élèves', 'Students'), icon: GraduationCap, value: data.counts.students, href: '/admin/students', color: 'bg-primary-50 text-primary-600' },
    { label: ui('Enseignants', 'Teachers'), icon: UserCheck, value: data.counts.teachers, href: '/admin/teachers', color: 'bg-info-light text-info' },
    { label: 'Parents',   icon: Users,         value: data.counts.parents,  href: '/admin/parents',  color: 'bg-success-light text-success' },
    { label: 'Classes',   icon: BookOpen,      value: data.counts.classes,  href: '/admin/academic/classes', color: 'bg-warning-light text-warning' },
  ];

  const totalToday = data.attendance_today.present + data.attendance_today.absent + data.attendance_today.late + data.attendance_today.excused;
  const presentRate = totalToday ? Math.round(((data.attendance_today.present + data.attendance_today.late) / totalToday) * 100) : 0;

  return (
    <div>
      <PageHeader
        title={`${ui('Bon retour', 'Welcome back')}${user ? `, ${user.first_name}` : ''}`}
        description={data.active_year
          ? `${ui('Année scolaire active', 'Active academic year')}: ${data.active_year.title}${data.active_term ? ` · ${data.active_term.name}` : ''}`
          : ui('Aucune année scolaire active — créez-en une dans les paramètres scolaires.', 'No active academic year — create one in Academic settings.')}
      />

      <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        {tiles.map((t) => {
          const Icon = t.icon;
          return (
            <Link key={t.label} href={t.href} className="block">
              <Card className="cursor-pointer transition-shadow hover:shadow-card">
                <CardContent className="py-4">
                  <div className="flex items-start justify-between">
                    <div>
                      <p className="text-sm text-secondary-500">{t.label}</p>
                      <p className="text-3xl font-semibold text-ink">{t.value}</p>
                    </div>
                    <div className={`rounded-button p-2 ${t.color}`}><Icon className="h-4 w-4" /></div>
                  </div>
                </CardContent>
              </Card>
            </Link>
          );
        })}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base">{ui('Présences du jour', 'Today’s attendance')}</CardTitle>
              <Link href="/admin/attendance" className="text-xs text-primary-600 hover:underline">{ui('Ouvrir', 'Open')} →</Link>
            </div>
          </CardHeader>
          <CardContent>
            {totalToday === 0 ? (
              <p className="py-4 text-center text-sm text-secondary-500">{ui('Aucune présence enregistrée aujourd’hui.', 'No attendance taken yet today.')}</p>
            ) : (
              <div>
                <div className="mb-4 grid grid-cols-4 gap-2 text-center">
                  <div><p className="text-2xl font-semibold text-success">{data.attendance_today.present}</p><p className="text-xs text-secondary-500">{ui('Présents', 'Present')}</p></div>
                  <div><p className="text-2xl font-semibold text-danger">{data.attendance_today.absent}</p><p className="text-xs text-secondary-500">{ui('Absents', 'Absent')}</p></div>
                  <div><p className="text-2xl font-semibold text-warning">{data.attendance_today.late}</p><p className="text-xs text-secondary-500">{ui('Retards', 'Late')}</p></div>
                  <div><p className="text-2xl font-semibold text-info">{data.attendance_today.excused}</p><p className="text-xs text-secondary-500">{ui('Excusés', 'Excused')}</p></div>
                </div>
                <div className="rounded-card bg-secondary-50 p-3 text-center">
                  <p className="text-sm">{ui('Taux de présence', 'Attendance rate')}: <span className="font-semibold text-success">{presentRate}%</span></p>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base">{ui('Aperçu financier', 'Finance overview')}</CardTitle>
              <Link href="/admin/finance" className="text-xs text-primary-600 hover:underline">{ui('Ouvrir', 'Open')} →</Link>
            </div>
          </CardHeader>
          <CardContent>
            <p className="mb-3 text-xs text-secondary-500">
              {ui(
                'Totaux cumulés de toutes les factures élèves actives. Ouvrez Finances pour le détail par élève.',
                'Combined totals for all active student invoices. Open Finance for the per-student breakdown.',
              )}
            </p>
            <ul className="space-y-2">
              <li className="flex items-center justify-between border-b border-secondary-100 pb-2">
                <span className="flex items-center gap-2 text-sm text-secondary-600"><TrendingUp className="h-4 w-4 text-success" />{ui('Encaissé', 'Collected')}</span>
                <span className="font-semibold text-success">{formatXAF(data.finance.collected)}</span>
              </li>
              <li className="flex items-center justify-between border-b border-secondary-100 pb-2">
                <span className="flex items-center gap-2 text-sm text-secondary-600"><Receipt className="h-4 w-4 text-warning" />{ui('Impayé', 'Outstanding')}</span>
                <span className="font-semibold text-warning">{formatXAF(data.finance.outstanding)}</span>
              </li>
              <li className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-sm text-secondary-600"><AlertTriangle className="h-4 w-4 text-danger" />{ui('En retard', 'Overdue')}</span>
                <span className="font-semibold text-danger">{formatXAF(data.finance.overdue)}</span>
              </li>
            </ul>
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base">{ui('Paiements récents', 'Recent payments')}</CardTitle>
              <Link href="/admin/finance/invoices" className="text-xs text-primary-600 hover:underline">{ui('Voir les factures', 'View invoices')} →</Link>
            </div>
          </CardHeader>
          <CardContent>
            {data.recent_payments.length === 0 ? (
              <p className="py-4 text-center text-sm text-secondary-500">{ui('Aucun paiement pour le moment.', 'No payments yet.')}</p>
            ) : (
              <ul className="divide-y divide-secondary-100">
                {data.recent_payments.map((p) => (
                  <li key={p.id} className="flex items-center justify-between py-2 text-sm">
                    <div>
                      <p className="font-medium text-ink">{p.student?.full_name ?? '—'}</p>
                      <p className="text-xs text-secondary-500">{p.paid_at} · {p.method.replace('_', ' ')} · {p.receipt_number}</p>
                    </div>
                    <span className="font-semibold text-success">{formatXAF(p.amount)}</span>
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
