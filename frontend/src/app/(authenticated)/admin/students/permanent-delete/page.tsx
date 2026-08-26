'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { AlertTriangle, Search } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { PageHeader } from '@/components/ui/PageHeader';
import { studentsApi } from '@/lib/api/students';
import type { Student } from '@/types/student';

type Reason = 'test_record' | 'duplicate_record' | 'registration_error';

export default function PermanentStudentDeletionPage() {
  const router = useRouter();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Student[]>([]);
  const [student, setStudent] = useState<Student | null>(null);
  const [confirmation, setConfirmation] = useState('');
  const [reason, setReason] = useState<Reason>('test_record');
  const [busy, setBusy] = useState(false);

  async function search() {
    if (query.trim().length < 2) return;
    setBusy(true);
    try { setResults((await studentsApi.list({ q: query.trim(), include_archived: true, per_page: 20 })).data); }
    catch { toast.error('Could not search student records.'); }
    finally { setBusy(false); }
  }
  async function remove() {
    if (!student || confirmation !== student.admission_number) { toast.error('Type the exact matriculation number to confirm.'); return; }
    setBusy(true);
    try {
      await studentsApi.permanentlyDelete(student.id, confirmation, reason);
      toast.success('The test record was permanently deleted and the action was audited.');
      router.push('/admin/students');
    } catch (error: any) {
      toast.error(error?.response?.data?.message ?? 'This record cannot be permanently deleted. Records with payments must be archived.');
    } finally { setBusy(false); }
  }

  return <div>
    <PageHeader breadcrumb={[{ label: 'Students', href: '/admin/students' }, { label: 'Permanent deletion' }]}
      title="Test data cleanup" description="Administrator-only deletion for test, duplicate, or erroneous records. Financially used records are protected." />
    <Card className="mb-6"><CardHeader><CardTitle>Find the record</CardTitle><CardDescription>Search by name or matriculation number, including archived students.</CardDescription></CardHeader><CardContent>
      <div className="flex gap-2"><Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Matriculation number or name" /><Button onClick={search} isLoading={busy} leftIcon={<Search className="h-4 w-4" />}>Search</Button></div>
      <div className="mt-4 divide-y divide-secondary-100">{results.map((item) => <button key={item.id} type="button" onClick={() => { setStudent(item); setConfirmation(''); }} className="flex w-full justify-between px-2 py-3 text-left hover:bg-secondary-50"><span>{item.full_name}</span><span className="font-mono text-sm">{item.admission_number} · {item.status}</span></button>)}</div>
    </CardContent></Card>
    {student && <Card className="border-danger/40"><CardHeader><div className="flex gap-2"><AlertTriangle className="h-6 w-6 text-danger" /><div><CardTitle>Permanently delete {student.full_name}?</CardTitle><CardDescription>This is irreversible. Report cards, enrollments, attendance, and grades for this student will be deleted.</CardDescription></div></div></CardHeader><CardContent className="space-y-4">
      <label className="block text-sm font-medium">Reason<select value={reason} onChange={(e) => setReason(e.target.value as Reason)} className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3"><option value="test_record">Test record</option><option value="duplicate_record">Duplicate record</option><option value="registration_error">Registration error</option></select></label>
      <Input label={`Type ${student.admission_number} to confirm`} value={confirmation} onChange={(e) => setConfirmation(e.target.value.toUpperCase())} />
      <Button variant="danger" onClick={remove} isLoading={busy} disabled={confirmation !== student.admission_number}>Delete permanently</Button>
    </CardContent></Card>}
  </div>;
}
