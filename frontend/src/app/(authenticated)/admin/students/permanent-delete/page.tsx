'use client';

import { useEffect, useState } from 'react';
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
  const [searching, setSearching] = useState(false);
  const [deleting, setDeleting] = useState(false);

  useEffect(() => {
    const term = query.trim();
    if (!term) { setResults([]); setSearching(false); return; }
    let cancelled = false;
    const timer = window.setTimeout(async () => {
      setSearching(true);
      try {
        const page = await studentsApi.list({ q: term, include_archived: true, per_page: 20 });
        if (!cancelled) setResults(page.data);
      } catch {
        if (!cancelled) toast.error('Could not search student records.');
      } finally {
        if (!cancelled) setSearching(false);
      }
    }, 250);
    return () => { cancelled = true; window.clearTimeout(timer); };
  }, [query]);

  async function remove() {
    const normalizedConfirmation = confirmation.trim();
    if (!student || normalizedConfirmation.localeCompare(student.admission_number, undefined, { sensitivity: 'accent' }) !== 0) { toast.error('Type the exact matriculation number to confirm.'); return; }
    setDeleting(true);
    try {
      await studentsApi.permanentlyDelete(student.id, normalizedConfirmation, reason);
      toast.success('The test record was permanently deleted and the action was audited.');
      router.push('/admin/students');
    } catch (error: any) {
      const validationErrors = error?.response?.data?.errors as Record<string, string[]> | undefined;
      const firstValidationError = validationErrors ? Object.values(validationErrors).flat()[0] : undefined;
      toast.error(firstValidationError ?? error?.response?.data?.message ?? 'This record cannot be permanently deleted. Records with payments must be archived.');
    } finally { setDeleting(false); }
  }

  return <div>
    <PageHeader breadcrumb={[{ label: 'Students', href: '/admin/students' }, { label: 'Permanent deletion' }]}
      title="Test data cleanup" description="Administrator-only deletion for test, duplicate, or erroneous records. Financially used records are protected." />
    <Card className="mb-6"><CardHeader><CardTitle>Find the record</CardTitle><CardDescription>Search by name, matriculation number, or class, including archived students.</CardDescription></CardHeader><CardContent>
      <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Start typing a name, matriculation number, or class" leftIcon={<Search className="h-4 w-4" />} hint={searching ? 'Searching…' : 'Results appear automatically from the first character.'} />
      <div className="mt-4 divide-y divide-secondary-100">{results.map((item) => <button key={item.id} type="button" onClick={() => { setStudent(item); setConfirmation(''); }} className="flex w-full justify-between gap-4 px-2 py-3 text-left hover:bg-secondary-50"><span><span className="block">{item.full_name}</span><span className="text-xs text-secondary-500">{item.class?.name ?? 'No class assigned'}</span></span><span className="font-mono text-sm">{item.admission_number} · {item.status}</span></button>)}</div>
    </CardContent></Card>
    {student && <Card className="border-danger/40"><CardHeader><div className="flex gap-2"><AlertTriangle className="h-6 w-6 text-danger" /><div><CardTitle>Permanently delete {student.full_name}?</CardTitle><CardDescription>This is irreversible. Report cards, enrollments, attendance, and grades for this student will be deleted.</CardDescription></div></div></CardHeader><CardContent className="space-y-4">
      <label className="block text-sm font-medium">Reason<select value={reason} onChange={(e) => setReason(e.target.value as Reason)} className="mt-1 h-10 w-full rounded-input border border-secondary-300 bg-surface px-3"><option value="test_record">Test record</option><option value="duplicate_record">Duplicate record</option><option value="registration_error">Registration error</option></select></label>
      <Input label={`Type ${student.admission_number} to confirm`} value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
      <Button variant="danger" onClick={remove} isLoading={deleting} disabled={deleting || confirmation.trim() === ''}>Delete permanently</Button>
    </CardContent></Card>}
  </div>;
}
