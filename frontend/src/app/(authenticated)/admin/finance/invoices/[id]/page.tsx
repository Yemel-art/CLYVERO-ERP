'use client';

import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { ArrowLeft, Plus, X, Download } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Skeleton } from '@/components/ui/Skeleton';
import { Badge } from '@/components/ui/Badge';
import { useInvoice, useRecordPayment, useCancelInvoice } from '@/hooks/finance';
import { financeApi } from '@/lib/api/finance';
import { PAYMENT_METHODS, type PaymentMethod, type InvoiceStatus, type PaymentRecord } from '@/types/finance';
import { useTranslation } from '@/hooks/useTranslation';
import { translateFeature } from '@/hooks/featureTranslations';
import { useAuthStore } from '@/store/auth';

const statusVariant: Record<InvoiceStatus, 'success' | 'warning' | 'danger' | 'info' | 'secondary'> = {
  draft: 'secondary', issued: 'info', partially_paid: 'warning',
  paid: 'success', overdue: 'danger', cancelled: 'secondary',
};

function formatXAF(n: number) {
  return new Intl.NumberFormat('fr-CM').format(Math.round(n)) + ' XAF';
}

export default function InvoiceDetailPage() {
  const router = useRouter();
  const role = useAuthStore((s) => s.user?.role.name);
  const financeBase = role === 'secretary' ? '/secretary/finance' : '/admin/finance';
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const { id } = useParams<{ id: string }>();
  const { data: inv, isLoading } = useInvoice(id);
  const record = useRecordPayment(id);
  const cancel = useCancelInvoice();
  const translation = useTranslation();
  const { language } = translation;
  const t = (key: string) => translateFeature(language, key) ?? translation.t(key);

  const [open, setOpen] = useState(false);
  const [paidAt, setPaidAt] = useState(new Date().toISOString().slice(0, 10));
  const [amount, setAmount] = useState<number>(0);
  const [method, setMethod] = useState<PaymentMethod>('cash');
  const [reference, setReference] = useState('');
  const [receiptPayment, setReceiptPayment] = useState<PaymentRecord | null>(null);
  const [receiptCopies, setReceiptCopies] = useState<1 | 2 | 3>(2);
  const [receiptLanguage, setReceiptLanguage] = useState<'fr' | 'en'>(language);
  const [downloadingReceipt, setDownloadingReceipt] = useState(false);

  if (isLoading || !inv) return <div className="space-y-4"><Skeleton className="h-8" /><Skeleton className="h-64" /></div>;

  const submit = async () => {
    if (amount <= 0) {
      toast.error(language === 'fr' ? 'Le montant doit être positif.' : 'Amount must be positive.');
      return;
    }
    if (amount > inv.balance + 0.01) {
      toast.error(language === 'fr'
        ? `Le montant ne peut pas dépasser le solde restant (${formatXAF(inv.balance)}).`
        : `Amount cannot exceed the outstanding balance (${formatXAF(inv.balance)}).`);
      return;
    }
    try {
      const p = await record.mutateAsync({ paid_at: paidAt, amount, method, reference: reference || undefined });
      toast.success(language === 'fr'
        ? `Paiement de ${formatXAF(p.amount)} enregistré (${p.receipt_number}).`
        : `Payment of ${formatXAF(p.amount)} recorded (${p.receipt_number}).`);
      setOpen(false);
      setAmount(0); setReference('');
      setReceiptLanguage(language);
      setReceiptPayment(p);
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? t('Could not record payment.'));
    }
  };

  const downloadReceipt = async () => {
    if (!receiptPayment) return;
    setDownloadingReceipt(true);
    try {
      const blob = await financeApi.downloadReceipt(receiptPayment.id, receiptCopies, receiptLanguage);
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `receipt-${receiptPayment.receipt_number}.pdf`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      URL.revokeObjectURL(url);
      toast.success(t('Receipt downloaded.'));
      setReceiptPayment(null);
    } catch (err: any) {
      toast.error(err?.response?.data?.message ?? t('Could not download the receipt.'));
    } finally {
      setDownloadingReceipt(false);
    }
  };

  const onCancel = async () => {
    const confirmation = language === 'fr'
      ? `Annuler la facture ${inv.invoice_number} ? Cette action est irréversible.`
      : `Cancel invoice ${inv.invoice_number}? This cannot be undone.`;
    if (!window.confirm(confirmation)) return;
    try { await cancel.mutateAsync(inv.id); toast.success(t('Invoice cancelled.')); }
    catch { toast.error(t('Could not cancel.')); }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: t('Home'), href: dashboardBase },
          { label: t('Finance'), href: financeBase },
          { label: t('Invoices'), href: `${financeBase}/invoices` },
          { label: inv.invoice_number },
        ]}
        title={`${t('Invoice')} ${inv.invoice_number}`}
        description={inv.student ? (language === 'fr'
          ? `Pour ${inv.student.full_name} (${inv.student.admission_number})`
          : `For ${inv.student.full_name} (${inv.student.admission_number})`) : ''}
        actions={<>
          <Button variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />} onClick={() => router.push(`${financeBase}/invoices`)}>{t('Back')}</Button>
          {inv.status !== 'paid' && inv.status !== 'cancelled' && (
            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => { setAmount(inv.balance); setOpen(true); }}>{t('Record payment')}</Button>
          )}
          {inv.status !== 'paid' && inv.status !== 'cancelled' && (
            <Button variant="danger" leftIcon={<X className="h-4 w-4" />} onClick={onCancel}>{t('Cancel invoice')}</Button>
          )}
        </>}
      />

      <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">{t('Total')}</p><p className="text-xl font-semibold text-ink">{formatXAF(inv.total)}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">{t('Paid')}</p><p className="text-xl font-semibold text-success">{formatXAF(inv.paid)}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">{t('Balance')}</p><p className={`text-xl font-semibold ${inv.balance > 0 ? 'text-danger' : 'text-success'}`}>{formatXAF(inv.balance)}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">{t('Status')}</p><Badge variant={statusVariant[inv.status]}>{inv.status.replace('_', ' ')}</Badge></CardContent></Card>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle>{t('Line items')}</CardTitle></CardHeader>
          <CardContent>
            <table className="w-full text-sm">
              <thead className="border-b border-secondary-200 text-left text-xs uppercase text-secondary-500">
                <tr><th className="py-2">{t('Description')}</th><th className="text-right">{t('Qty')}</th><th className="text-right">{t('Unit')}</th><th className="text-right">{t('Total')}</th></tr>
              </thead>
              <tbody className="divide-y divide-secondary-100">
                {(inv.items ?? []).map((it) => (
                  <tr key={it.id}>
                    <td className="py-2">{it.description}</td>
                    <td className="text-right">{it.quantity}</td>
                    <td className="text-right">{formatXAF(it.unit_amount)}</td>
                    <td className="text-right font-medium">{formatXAF(it.line_total)}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot className="border-t-2 border-secondary-200 text-sm">
                <tr><td colSpan={3} className="pt-2 text-right text-secondary-500">{t('Subtotal')}</td><td className="pt-2 text-right">{formatXAF(inv.subtotal)}</td></tr>
                <tr><td colSpan={3} className="text-right text-secondary-500">{t('Discount')}</td><td className="text-right">{formatXAF(inv.discount)}</td></tr>
                <tr><td colSpan={3} className="text-right font-semibold">{t('Total')}</td><td className="text-right font-semibold">{formatXAF(inv.total)}</td></tr>
              </tfoot>
            </table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>{t('Payments')}</CardTitle></CardHeader>
          <CardContent>
            {(inv.payments ?? []).length === 0 ? (
              <p className="py-4 text-center text-sm text-secondary-500">{t('No payments yet.')}</p>
            ) : (
              <ul className="divide-y divide-secondary-100">
                {(inv.payments ?? []).map((p) => (
                  <li key={p.id} className="flex items-center justify-between py-3">
                    <div>
                      <p className="font-medium text-ink">{formatXAF(p.amount)}</p>
                      <p className="text-xs text-secondary-500">
                        {p.paid_at} · {p.method.replace('_', ' ')}{p.reference ? ` · ${p.reference}` : ''}
                      </p>
                      <p className="font-mono text-xs text-secondary-400">{p.receipt_number}</p>
                    </div>
                    <Button size="sm" variant="ghost" leftIcon={<Download className="h-4 w-4" />}
                      onClick={() => {
                        setReceiptLanguage(language);
                        setReceiptPayment(p);
                      }}>{t('Receipt')}</Button>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <Modal open={open} onClose={() => setOpen(false)} title={t('Record payment')} size="md"
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>{t('Cancel')}</Button>
          <Button onClick={submit} isLoading={record.isPending} disabled={amount <= 0}>{t('Record payment')}</Button>
        </>}>
        <div className="space-y-3">
          <Input type="date" label={t('Date received')} value={paidAt} onChange={(e) => setPaidAt(e.target.value)} />
          <Input type="number" min={0} step={500} label={t('Amount (XAF)')} value={amount} onChange={(e) => setAmount(Number(e.target.value))} />
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">{t('Payment method')}</label>
            <select value={method} onChange={(e) => setMethod(e.target.value as PaymentMethod)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              {PAYMENT_METHODS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
            </select>
          </div>
          <Input label={t('Reference (transaction ID, cheque number…)')} value={reference} onChange={(e) => setReference(e.target.value)} />
        </div>
      </Modal>

      <Modal open={Boolean(receiptPayment)} onClose={() => setReceiptPayment(null)}
        title={t('Download payment receipt')}
        description={receiptPayment ? `${receiptPayment.receipt_number} · ${formatXAF(receiptPayment.amount)}` : undefined}
        size="sm"
        footer={<>
          <Button variant="outline" onClick={() => setReceiptPayment(null)}>{t('Cancel')}</Button>
          <Button leftIcon={<Download className="h-4 w-4" />} onClick={downloadReceipt}
            isLoading={downloadingReceipt}>{t('Download PDF')}</Button>
        </>}>
        <div className="space-y-4">
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">{t('Language')}</label>
            <select value={receiptLanguage} onChange={(e) => setReceiptLanguage(e.target.value as 'fr' | 'en')}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="fr">Français</option>
              <option value="en">English</option>
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">{t('Number of copies')}</label>
            <select value={receiptCopies} onChange={(e) => setReceiptCopies(Number(e.target.value) as 1 | 2 | 3)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value={1}>1 — {t('Original')}</option>
              <option value={2}>2 — {t('Original + duplicate')}</option>
              <option value={3}>3 — {t('Original + duplicate + triplicate')}</option>
            </select>
          </div>
        </div>
      </Modal>
    </div>
  );
}
