'use client';

import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Calculator, Edit3, PlayCircle, Save } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Modal } from '@/components/ui/Modal';
import { Skeleton } from '@/components/ui/Skeleton';
import {
  useAcademicDecisions,
  useAcademicYears,
  useEvaluateAcademicDecisions,
  usePromotionPolicy,
  useSavePromotionPolicy,
  useSubjects,
  useUpdateAcademicDecision,
} from '@/hooks/academic';
import type { AcademicDecision, PromotionPolicy } from '@/types/academic';
import { useTranslation } from '@/hooks/useTranslation';

type PolicyForm = Omit<PromotionPolicy, 'id' | 'academic_year_id' | 'is_active'>;

const DEFAULT_POLICY: PolicyForm = {
  name: 'Standard promotion policy',
  grading_scale: 20,
  passing_average: 10,
  minimum_subject_mark: 8,
  maximum_failed_subjects: 2,
  critical_subject_ids: [],
  failure_conditions: { match: 'any' },
  exclusion_conditions: { match: 'any' },
  allow_class_council_override: true,
};

const decisionVariant = {
  promoted: 'success',
  repeating: 'warning',
  excluded: 'danger',
} as const;

export default function ProgressionPage() {
  const { t } = useTranslation();
  const { data: years, isLoading: yearsLoading } = useAcademicYears();
  const { data: subjects } = useSubjects({ is_active: true });
  const [yearId, setYearId] = useState('');
  const { data: policy, isLoading: policyLoading } = usePromotionPolicy(yearId || undefined);
  const { data: decisions, isLoading: decisionsLoading } = useAcademicDecisions(yearId || undefined);
  const savePolicy = useSavePromotionPolicy(yearId);
  const evaluate = useEvaluateAcademicDecisions(yearId);
  const updateDecision = useUpdateAcademicDecision(yearId);
  const [form, setForm] = useState<PolicyForm>(DEFAULT_POLICY);
  const [editing, setEditing] = useState<AcademicDecision | null>(null);
  const [editForm, setEditForm] = useState({
    final_decision: 'promoted' as AcademicDecision['final_decision'],
    teacher_appreciation: '',
    class_council_recommendation: '',
    override_reason: '',
  });

  useEffect(() => {
    if (!yearId && years?.length) setYearId(years.find((year) => year.status === 'active')?.id ?? years[0].id);
  }, [yearId, years]);

  useEffect(() => {
    if (policy) {
      const { id: _id, academic_year_id: _year, is_active: _active, ...values } = policy;
      setForm(values);
    } else if (!policyLoading) {
      setForm(DEFAULT_POLICY);
    }
  }, [policy, policyLoading]);

  const selectedYear = useMemo(() => years?.find((year) => year.id === yearId), [yearId, years]);

  const save = async () => {
    try {
      await savePolicy.mutateAsync(form);
      toast.success(t('Promotion policy saved.'));
    } catch {
      toast.error(t('Could not save the promotion policy.'));
    }
  };

  const runEvaluation = async () => {
    try {
      const result = await evaluate.mutateAsync();
      toast.success(t(`${result.length} academic decisions generated.`));
    } catch {
      toast.error(t('Final grades are incomplete or the policy is not configured.'));
    }
  };

  const openDecision = (decision: AcademicDecision) => {
    setEditing(decision);
    setEditForm({
      final_decision: decision.final_decision,
      teacher_appreciation: decision.teacher_appreciation ?? '',
      class_council_recommendation: decision.class_council_recommendation ?? '',
      override_reason: '',
    });
  };

  const saveDecision = async () => {
    if (!editing) return;
    const decisionChanged = editForm.final_decision !== editing.final_decision;
    if (decisionChanged && !editForm.override_reason.trim()) {
      toast.error(t('An override reason is required.'));
      return;
    }
    try {
      await updateDecision.mutateAsync({
        id: editing.id,
        payload: {
          final_decision: editForm.final_decision,
          teacher_appreciation: editForm.teacher_appreciation || null,
          class_council_recommendation: editForm.class_council_recommendation || null,
          ...(decisionChanged ? { override_reason: editForm.override_reason } : {}),
        },
      });
      toast.success(t('Academic decision updated.'));
      setEditing(null);
    } catch {
      toast.error(t('Could not update the academic decision.'));
    }
  };

  if (yearsLoading) return <Skeleton className="h-96" />;

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: t('Home'), href: '/admin/dashboard' },
          { label: t('Academic structure'), href: '/admin/academic' },
          { label: t('Academic progression') },
        ]}
        title={t('Academic progression')}
        description={t('Configure promotion rules, generate final decisions, and prepare the next school year.')}
      />

      <div className="mb-5 max-w-sm">
        <label className="mb-1.5 block text-sm font-medium text-secondary-700">{t('Academic year')}</label>
        <select
          className="h-10 w-full rounded-input border border-secondary-300 bg-surface px-3 text-sm"
          value={yearId}
          onChange={(event) => setYearId(event.target.value)}
        >
          {(years ?? []).map((year) => <option key={year.id} value={year.id}>{year.title} · {t(year.status)}</option>)}
        </select>
      </div>

      <div className="grid gap-5 xl:grid-cols-[minmax(0,420px)_1fr]">
        <Card>
          <CardHeader><CardTitle>{t('Promotion policy')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            {policyLoading ? <Skeleton className="h-72" /> : <>
              <Input label={t('Policy name')} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
              <div className="grid grid-cols-2 gap-3">
                <Input type="number" min="1" step="0.01" label={t('Grading scale')} value={form.grading_scale} onChange={(event) => setForm({ ...form, grading_scale: Number(event.target.value) })} />
                <Input type="number" min="0" step="0.01" label={t('Passing average')} value={form.passing_average} onChange={(event) => setForm({ ...form, passing_average: Number(event.target.value) })} />
                <Input type="number" min="0" step="0.01" label={t('Minimum subject mark')} value={form.minimum_subject_mark} onChange={(event) => setForm({ ...form, minimum_subject_mark: Number(event.target.value) })} />
                <Input type="number" min="0" label={t('Maximum failed subjects')} value={form.maximum_failed_subjects} onChange={(event) => setForm({ ...form, maximum_failed_subjects: Number(event.target.value) })} />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <Input type="number" min="0" step="0.01" label={t('Repeat below average')} value={form.failure_conditions.average_below ?? ''} onChange={(event) => setForm({ ...form, failure_conditions: { ...form.failure_conditions, average_below: event.target.value ? Number(event.target.value) : undefined } })} />
                <Input type="number" min="0" step="0.01" label={t('Exclude below average')} value={form.exclusion_conditions.average_below ?? ''} onChange={(event) => setForm({ ...form, exclusion_conditions: { ...form.exclusion_conditions, average_below: event.target.value ? Number(event.target.value) : undefined } })} />
              </div>
              <div>
                <p className="mb-2 text-sm font-medium text-secondary-700">{t('Subjects that cannot be failed')}</p>
                <div className="max-h-36 space-y-2 overflow-y-auto rounded-input border border-secondary-200 p-3">
                  {(subjects ?? []).map((subject) => (
                    <label key={subject.id} className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={form.critical_subject_ids.includes(subject.id)}
                        onChange={(event) => setForm({
                          ...form,
                          critical_subject_ids: event.target.checked
                            ? [...form.critical_subject_ids, subject.id]
                            : form.critical_subject_ids.filter((id) => id !== subject.id),
                        })}
                      />
                      {subject.name}
                    </label>
                  ))}
                  {!subjects?.length && <p className="text-sm text-secondary-500">{t('No active subjects.')}</p>}
                </div>
              </div>
              <label className="flex items-start gap-2 text-sm text-secondary-700">
                <input type="checkbox" className="mt-0.5" checked={form.allow_class_council_override} onChange={(event) => setForm({ ...form, allow_class_council_override: event.target.checked })} />
                {t('Allow a documented class council override')}
              </label>
              <Button className="w-full" leftIcon={<Save className="h-4 w-4" />} onClick={save} isLoading={savePolicy.isPending}>{t('Save policy')}</Button>
            </>}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-4">
            <div>
              <CardTitle>{t('Final academic decisions')}</CardTitle>
              <p className="mt-1 text-sm text-secondary-500">{selectedYear?.title}</p>
            </div>
            <Button leftIcon={<PlayCircle className="h-4 w-4" />} onClick={runEvaluation} isLoading={evaluate.isPending} disabled={!policy}>{t('Generate decisions')}</Button>
          </CardHeader>
          <CardContent className="px-0">
            {decisionsLoading ? <Skeleton className="mx-6 h-48" /> : !decisions?.length ? (
              <div className="px-6 py-12 text-center">
                <Calculator className="mx-auto h-8 w-8 text-secondary-300" />
                <p className="mt-3 text-sm text-secondary-500">{t('No final decisions have been generated for this year.')}</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="border-y border-secondary-200 bg-secondary-50 text-xs uppercase text-secondary-500">
                    <tr><th className="px-5 py-3">{t('Student')}</th><th className="px-4 py-3">{t('Class')}</th><th className="px-4 py-3">{t('Average')}</th><th className="px-4 py-3">{t('Decision')}</th><th className="px-4 py-3" /></tr>
                  </thead>
                  <tbody className="divide-y divide-secondary-100">
                    {decisions.map((decision) => (
                      <tr key={decision.id}>
                        <td className="px-5 py-3"><p className="font-medium text-ink">{decision.student?.full_name}</p><p className="text-xs text-secondary-500">{decision.student?.admission_number}</p></td>
                        <td className="px-4 py-3">{decision.class?.name ?? '—'}</td>
                        <td className="px-4 py-3 font-medium">{decision.final_average?.toFixed(2) ?? '—'}</td>
                        <td className="px-4 py-3"><Badge variant={decisionVariant[decision.final_decision]}>{decision.decision_label}</Badge></td>
                        <td className="px-4 py-3"><Button size="sm" variant="ghost" leftIcon={<Edit3 className="h-4 w-4" />} onClick={() => openDecision(decision)}>{t('Review')}</Button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <Modal
        open={Boolean(editing)}
        onClose={() => setEditing(null)}
        title={t('Class council review')}
        size="lg"
        footer={<><Button variant="outline" onClick={() => setEditing(null)}>{t('Cancel')}</Button><Button onClick={saveDecision} isLoading={updateDecision.isPending}>{t('Save review')}</Button></>}
      >
        <div className="space-y-4">
          <div><p className="font-medium text-ink">{editing?.student?.full_name}</p><p className="text-sm text-secondary-500">{editing?.class?.name} · {t('Computed decision')}: {editing?.computed_decision}</p></div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-secondary-700">{t('Final decision')}</label>
            <select className="h-10 w-full rounded-input border border-secondary-300 bg-surface px-3 text-sm" value={editForm.final_decision} onChange={(event) => setEditForm({ ...editForm, final_decision: event.target.value as AcademicDecision['final_decision'] })}>
              <option value="promoted">{t('Promoted')}</option><option value="repeating">{t('Repeating')}</option><option value="excluded">{t('Excluded')}</option>
            </select>
          </div>
          <label className="block text-sm font-medium text-secondary-700">{t('Teacher appreciation')}<textarea className="mt-1.5 min-h-24 w-full rounded-input border border-secondary-300 p-3 text-sm" value={editForm.teacher_appreciation} onChange={(event) => setEditForm({ ...editForm, teacher_appreciation: event.target.value })} /></label>
          <label className="block text-sm font-medium text-secondary-700">{t('Class council recommendation')}<textarea className="mt-1.5 min-h-24 w-full rounded-input border border-secondary-300 p-3 text-sm" value={editForm.class_council_recommendation} onChange={(event) => setEditForm({ ...editForm, class_council_recommendation: event.target.value })} /></label>
          {editing && editForm.final_decision !== editing.final_decision && <label className="block text-sm font-medium text-secondary-700">{t('Override reason')}<textarea required className="mt-1.5 min-h-20 w-full rounded-input border border-secondary-300 p-3 text-sm" value={editForm.override_reason} onChange={(event) => setEditForm({ ...editForm, override_reason: event.target.value })} /></label>}
        </div>
      </Modal>
    </div>
  );
}
