'use client';

import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { Search, UserPlus, X } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { studentsApi } from '@/lib/api/students';
import { useAttachChild, useDetachChild } from '@/hooks/parents';
import type { Student } from '@/types/student';
import type { ParentGuardian } from '@/types/parent';

const RELATIONSHIPS = ['Father', 'Mother', 'Guardian', 'Uncle', 'Aunt', 'Sibling', 'Other'];

function useDebounced<T>(value: T, ms = 300): T {
  const [d, set] = useState(value);
  useEffect(() => { const id = setTimeout(() => set(value), ms); return () => clearTimeout(id); }, [value, ms]);
  return d;
}

export function LinkChildPanel({ parent }: { parent: ParentGuardian }) {
  const [search, setSearch] = useState('');
  const debouncedQ = useDebounced(search, 300);
  const [results, setResults] = useState<Student[]>([]);
  const [picked, setPicked] = useState<Student | null>(null);
  const [relationship, setRelationship] = useState(RELATIONSHIPS[0]);
  const [isPrimary, setIsPrimary] = useState(false);
  const [canPickup, setCanPickup] = useState(true);

  const attach = useAttachChild(parent.id);
  const detach = useDetachChild(parent.id);

  const linkedIds = new Set((parent.children ?? []).map((c) => c.id));

  // Debounced search for students.
  useEffect(() => {
    if (debouncedQ.length < 2) { setResults([]); return; }
    let cancelled = false;
    (async () => {
      try {
        const page = await studentsApi.list({ q: debouncedQ, per_page: 8 });
        if (!cancelled) setResults(page.data);
      } catch { /* swallow */ }
    })();
    return () => { cancelled = true; };
  }, [debouncedQ]);

  const onAttach = async () => {
    if (!picked) return;
    try {
      await attach.mutateAsync({
        student_id: picked.id,
        relationship,
        is_primary: isPrimary,
        can_pickup: canPickup,
      });
      toast.success(`${picked.full_name} linked as ${relationship}.`);
      setPicked(null);
      setIsPrimary(false);
      setCanPickup(true);
      setSearch('');
    } catch {
      toast.error('Could not link the child.');
    }
  };

  const onDetach = async (studentId: string, name: string) => {
    try {
      await detach.mutateAsync(studentId);
      toast.success(`${name} unlinked.`);
    } catch {
      toast.error('Could not unlink the child.');
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Children</CardTitle>
        <CardDescription>Students linked to this parent. The primary contact appears first on the student profile.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        {/* Linked children */}
        {(parent.children?.length ?? 0) === 0 ? (
          <p className="text-sm text-secondary-500">No children linked yet.</p>
        ) : (
          <ul className="space-y-2">
            {parent.children!.map((c) => (
              <li key={c.id} className="flex items-center gap-3 rounded-card border border-secondary-200 p-3">
                {c.photo_url
                  ? <img src={c.photo_url} alt="" className="h-10 w-10 rounded-full object-cover" />
                  : <Avatar name={c.full_name} size="sm" />}
                <div className="flex-1">
                  <p className="font-medium text-ink">{c.full_name}</p>
                  <p className="text-xs text-secondary-500">{c.admission_number} · {c.pivot.relationship}</p>
                </div>
                <div className="flex items-center gap-1">
                  {c.pivot.is_primary && <Badge variant="info">Primary</Badge>}
                  {c.pivot.can_pickup && <Badge variant="success">Can pick up</Badge>}
                </div>
                <button onClick={() => onDetach(c.id, c.full_name)}
                  className="rounded-button p-1.5 text-secondary-500 hover:bg-danger-light hover:text-danger" aria-label="Unlink">
                  <X className="h-4 w-4" />
                </button>
              </li>
            ))}
          </ul>
        )}

        {/* Search & attach */}
        <div className="rounded-card border border-secondary-200 bg-secondary-50 p-4">
          <p className="mb-3 text-sm font-medium text-secondary-700">Link a child</p>
          {picked ? (
            <div className="space-y-3">
              <div className="flex items-center gap-3 rounded-card bg-surface p-2">
                {picked.photo_url
                  ? <img src={picked.photo_url} alt="" className="h-10 w-10 rounded-full object-cover" />
                  : <Avatar name={picked.full_name} size="sm" />}
                <div className="flex-1">
                  <p className="font-medium text-ink">{picked.full_name}</p>
                  <p className="text-xs text-secondary-500">{picked.admission_number}</p>
                </div>
                <button onClick={() => setPicked(null)}
                  className="rounded-button p-1.5 text-secondary-500 hover:bg-secondary-100"><X className="h-4 w-4" /></button>
              </div>
              <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-secondary-700">Relationship</label>
                  <select value={relationship} onChange={(e) => setRelationship(e.target.value)}
                    className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                    {RELATIONSHIPS.map((r) => <option key={r} value={r}>{r}</option>)}
                  </select>
                </div>
                <div className="flex flex-col gap-2 pt-6">
                  <label className="flex items-center gap-2 text-sm text-secondary-700">
                    <input type="checkbox" checked={isPrimary} onChange={(e) => setIsPrimary(e.target.checked)}
                      className="rounded border-secondary-300 text-primary-600" />
                    Set as primary contact
                  </label>
                  <label className="flex items-center gap-2 text-sm text-secondary-700">
                    <input type="checkbox" checked={canPickup} onChange={(e) => setCanPickup(e.target.checked)}
                      className="rounded border-secondary-300 text-primary-600" />
                    Authorized to pick up
                  </label>
                </div>
              </div>
              <div className="flex justify-end">
                <Button leftIcon={<UserPlus className="h-4 w-4" />} isLoading={attach.isPending} onClick={onAttach}>
                  Link child
                </Button>
              </div>
            </div>
          ) : (
            <>
              <Input
                placeholder="Search students by name or admission number…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                leftIcon={<Search className="h-4 w-4" />}
              />
              {results.length > 0 && (
                <ul className="mt-3 max-h-60 space-y-1 overflow-y-auto">
                  {results.map((s) => {
                    const alreadyLinked = linkedIds.has(s.id);
                    return (
                      <li key={s.id}>
                        <button
                          disabled={alreadyLinked}
                          onClick={() => setPicked(s)}
                          className="flex w-full items-center gap-3 rounded-button p-2 text-left text-sm hover:bg-surface disabled:cursor-not-allowed disabled:opacity-50"
                        >
                          {s.photo_url
                            ? <img src={s.photo_url} alt="" className="h-8 w-8 rounded-full object-cover" />
                            : <Avatar name={s.full_name} size="sm" />}
                          <span className="flex-1">
                            <span className="font-medium text-ink">{s.full_name}</span>
                            <span className="ml-2 text-xs text-secondary-500">{s.admission_number}</span>
                          </span>
                          {alreadyLinked && <Badge variant="secondary">Already linked</Badge>}
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
