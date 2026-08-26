'use client';

import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';
import { useParentDashboard } from '@/hooks/dashboard';

const money = (amount: number) => `${new Intl.NumberFormat('fr-CM').format(Math.round(amount))} XAF`;

export default function ParentTuitionPage() {
  const { data } = useParentDashboard();
  return <div>
    <PageHeader breadcrumb={[{ label: 'Home', href: '/parent/dashboard' }, { label: 'Tuition' }]}
      title="Tuition balances" description="Payments are recorded by the school office after physical payment." />
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      {(data?.children ?? []).map((child) => <Card key={child.id}><CardContent className="py-5">
        <p className="font-semibold text-ink">{child.full_name}</p>
        <p className="text-xs text-secondary-500">{child.admission_number} · {child.class ?? 'No class'}</p>
        <div className="mt-4 flex justify-between rounded-card bg-secondary-50 p-3">
          <span className="text-sm text-secondary-600">Outstanding balance</span>
          <span className={child.balance > 0 ? 'font-bold text-danger' : 'font-bold text-success'}>{money(child.balance)}</span>
        </div>
      </CardContent></Card>)}
    </div>
  </div>;
}
