'use client';

import { useRouter } from 'next/navigation';
import { AlertTriangle, CalendarDays, ChevronRight, Receipt, Users, Wallet } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useFees, useFinanceSummary, useInvoices } from '@/hooks/finance';
import { useAuthStore } from '@/store/auth';

function formatXAF(value: number) {
  return `${new Intl.NumberFormat('fr-CM').format(Math.round(value))} XAF`;
}

export default function FinanceHubPage() {
  const router = useRouter();
  const role = useAuthStore((s) => s.user?.role.name);
  const financeBase = role === 'secretary' ? '/secretary/finance' : '/admin/finance';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const { data: fees } = useFees();
  const { data: invoices } = useInvoices({ page: 1, per_page: 25 });
  const { data: summary, isLoading } = useFinanceSummary();

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: dashboardBase }, { label: 'Finance' }]}
        title="Finance"
        description="Actual collections, balances, invoices, and receipts calculated by the server."
      />

      {isLoading || !summary ? <Skeleton className="mb-6 h-28" /> : (
        <>
          <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card><CardContent className="py-4">
              <p className="text-sm text-secondary-500">Amount collected today</p>
              <p className="text-xl font-semibold text-success">{formatXAF(summary.collected_today)}</p>
            </CardContent></Card>
            <Card><CardContent className="py-4">
              <p className="text-sm text-secondary-500">Total funds collected</p>
              <p className="text-xl font-semibold text-success">{formatXAF(summary.total_collected)}</p>
            </CardContent></Card>
            <Card><CardContent className="py-4">
              <p className="text-sm text-secondary-500">Outstanding balances</p>
              <p className="text-xl font-semibold text-warning">{formatXAF(summary.total_outstanding)}</p>
            </CardContent></Card>
            <Card><CardContent className="py-4">
              <p className="flex items-center gap-1 text-sm text-secondary-500"><AlertTriangle className="h-3 w-3 text-danger" />Overdue</p>
              <p className="text-xl font-semibold text-danger">{formatXAF(summary.total_overdue)}</p>
            </CardContent></Card>
          </div>

          <Card className="mb-6">
            <CardHeader><CardTitle className="flex items-center gap-2"><CalendarDays className="h-5 w-5" />Today · {summary.date}</CardTitle></CardHeader>
            <CardContent>
              <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-card bg-secondary-50 p-4"><p className="text-sm text-secondary-500">Students registered</p><p className="text-2xl font-semibold">{summary.students_registered_today}</p></div>
                <div className="rounded-card bg-secondary-50 p-4"><p className="text-sm text-secondary-500">Payments received</p><p className="text-2xl font-semibold">{summary.payments_received_today}</p></div>
                <div className="rounded-card bg-success-light p-4"><p className="text-sm text-secondary-600">Collected today</p><p className="text-2xl font-semibold text-success">{formatXAF(summary.collected_today)}</p></div>
              </div>
              {summary.recent_payments.length === 0 ? (
                <p className="py-3 text-center text-sm text-secondary-500">No valid payments recorded today.</p>
              ) : (
                <div className="overflow-x-auto rounded-card border border-secondary-200">
                  <table className="min-w-full divide-y divide-secondary-200 text-sm">
                    <thead className="bg-secondary-50 text-left text-secondary-600"><tr><th className="px-4 py-3">Student</th><th className="px-4 py-3">Receipt</th><th className="px-4 py-3 text-right">Paid</th><th className="px-4 py-3 text-right">Balance</th></tr></thead>
                    <tbody className="divide-y divide-secondary-100">
                      {summary.recent_payments.map((payment) => (
                        <tr key={payment.id} className="cursor-pointer hover:bg-secondary-50" onClick={() => router.push(`${financeBase}/invoices/${payment.invoice_id}`)}>
                          <td className="px-4 py-3"><p className="font-medium">{payment.student?.full_name ?? 'Unknown student'}</p><p className="text-xs text-secondary-500">{payment.student?.admission_number}</p></td>
                          <td className="px-4 py-3 font-mono text-xs">{payment.receipt_number}</td>
                          <td className="px-4 py-3 text-right font-semibold text-success">{formatXAF(payment.amount)}</td>
                          <td className="px-4 py-3 text-right font-semibold text-warning">{formatXAF(payment.invoice_balance)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Card className="cursor-pointer transition-shadow hover:shadow-card" onClick={() => router.push(`${financeBase}/fees`)}>
          <CardContent className="flex items-start justify-between py-5"><div className="flex gap-3"><Wallet className="h-5 w-5 text-primary-600" /><div><p className="text-lg font-semibold">Fee structures</p><p className="text-sm text-secondary-500">{fees?.length ?? 0} configured fees.</p></div></div><ChevronRight className="h-5 w-5 text-secondary-400" /></CardContent>
        </Card>
        <Card className="cursor-pointer transition-shadow hover:shadow-card" onClick={() => router.push(`${financeBase}/invoices`)}>
          <CardContent className="flex items-start justify-between py-5"><div className="flex gap-3"><Receipt className="h-5 w-5 text-primary-600" /><div><p className="text-lg font-semibold">Student accounts</p><p className="text-sm text-secondary-500">{invoices?.meta.total ?? 0} invoices · open the paginated ledger for details.</p></div></div><ChevronRight className="h-5 w-5 text-secondary-400" /></CardContent>
        </Card>
      </div>
      <p className="mt-4 flex items-center gap-2 text-xs text-secondary-500"><Users className="h-4 w-4" />Voided payments are retained for audit and excluded from every collection total.</p>
    </div>
  );
}
