'use client';

import { useState, useEffect, type KeyboardEvent } from 'react';
import { useParams, useSearchParams, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { ArrowLeft, Save, Send, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { useClass } from '@/hooks/academic';
import { useAssessments, useAssessment, useRecordEntries, usePublishAssessment, useDeleteAssessment } from '@/hooks/grades';
import { useTranslation } from '@/hooks/useTranslation';
import { useAuthStore } from '@/store/auth';

export default function SubjectGradebookPage() {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const gradesBase = user?.role.name === 'teacher' ? '/teacher/grades' : '/admin/grades';
  const dashboardBase = user?.role.name === 'teacher' ? '/teacher/dashboard' : '/admin/dashboard';
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const { classId, subjectId } = useParams<{ classId: string; subjectId: string }>();
  const search = useSearchParams();
  const termId = search.get('term') ?? undefined;
  const focusAssessmentId = search.get('assessment') ?? undefined;

  const { data: c } = useClass(classId);
  const { data: list, isLoading } = useAssessments({ class_id: classId, subject_id: subjectId, term_id: termId });
  const [selectedId, setSelectedId] = useState<string | undefined>(focusAssessmentId);

  // Auto-select first assessment if none focused.
  useEffect(() => {
    if (!selectedId && list && list.length > 0) {
      setSelectedId(list[0].id);
    }
  }, [list, selectedId]);

  const { data: assessment } = useAssessment(selectedId);
  const record = useRecordEntries(selectedId ?? '');
  const publish = usePublishAssessment();
  const remove = useDeleteAssessment();

  const subject = (c?.subjects ?? []).find((s) => s.id === subjectId);

  const [scores, setScores] = useState<Record<string, string>>({});
  const [comments, setComments] = useState<Record<string, string>>({});
  const [isDirty, setIsDirty] = useState(false);
  const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);

  // Hydrate scores from the loaded assessment.
  useEffect(() => {
    if (!assessment?.entries) return;
    const s: Record<string, string> = {};
    const c: Record<string, string> = {};
    assessment.entries.forEach((e) => {
      if (e.score !== null) s[e.student_id] = String(e.score);
      if (e.comment) c[e.student_id] = e.comment;
    });
    setScores(s); setComments(c); setIsDirty(false);
  }, [assessment?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const submit = async (showToast = true): Promise<boolean> => {
    if (!assessment || record.isPending) return false;
    const max = assessment.max_score;
    try {
      const entries = (assessment.entries ?? []).map((e) => {
        const raw = scores[e.student_id];
        const score = raw === '' || raw === undefined ? null : Number(raw);
        if (score !== null && (Number.isNaN(score) || score < 0 || score > max)) {
          throw new Error(language === 'fr'
            ? `La note de ${e.student?.full_name ?? 'l’élève'} doit être comprise entre 0 et ${max}.`
            : `Score for ${e.student?.full_name ?? 'student'} must be between 0 and ${max}.`);
        }
        return { student_id: e.student_id, score, comment: comments[e.student_id] || undefined };
      });
      await record.mutateAsync(entries);
      setIsDirty(false);
      setLastSavedAt(new Date());
      if (showToast) toast.success(ui('Notes enregistrées.', 'Grades saved.'));
      return true;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : ui('Impossible d’enregistrer les notes.', 'Could not save grades.');
      toast.error(message);
      return false;
    }
  };

  useEffect(() => {
    if (!isDirty || !assessment || record.isPending) return;
    const timer = window.setTimeout(() => {
      void submit(false);
    }, 1500);
    return () => window.clearTimeout(timer);
  }, [scores, comments, isDirty]); // eslint-disable-line react-hooks/exhaustive-deps

  const focusCell = (row: number, column: 'score' | 'comment') => {
    const input = document.querySelector<HTMLInputElement>(`[data-grade-row="${row}"][data-grade-column="${column}"]`);
    input?.focus();
    input?.select();
  };

  const navigateGrid = (
    event: KeyboardEvent<HTMLInputElement>,
    row: number,
    column: 'score' | 'comment',
    rowCount: number,
  ) => {
    if (event.key === 'Enter' || event.key === 'ArrowDown') {
      event.preventDefault();
      focusCell(Math.min(row + 1, rowCount - 1), column);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      focusCell(Math.max(row - 1, 0), column);
    }
  };

  const onPublish = async () => {
    if (!assessment) return;
    if (!window.confirm(ui('Publier cette évaluation ? Les élèves et les parents verront les notes.', 'Publish this assessment? Students and parents will see the grades.'))) return;
    const saved = await submit(false);
    if (!saved) return;
    try {
      await publish.mutateAsync(assessment.id);
      toast.success(ui('Évaluation publiée.', 'Assessment published.'));
    } catch {
      toast.error(ui('Impossible de publier.', 'Could not publish.'));
    }
  };

  const onDelete = async () => {
    if (!assessment) return;
    if (!window.confirm(language === 'fr' ? `Supprimer « ${assessment.title} » ? Cette action est irréversible.` : `Delete "${assessment.title}"? This cannot be undone.`)) return;
    try {
      await remove.mutateAsync(assessment.id);
      toast.success(ui('Évaluation supprimée.', 'Assessment deleted.'));
      router.push(`${gradesBase}/${classId}?term=${termId}`);
    } catch {
      toast.error(ui('Impossible de supprimer.', 'Could not delete.'));
    }
  };

  if (isLoading || !c) return <div className="space-y-4"><Skeleton className="h-8" /><Skeleton className="h-64" /></div>;

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: ui('Accueil', 'Home'), href: dashboardBase },
          { label: ui('Notes', 'Grades'), href: gradesBase },
          { label: c.name, href: `${gradesBase}/${c.id}?term=${termId}` },
          { label: subject?.name ?? ui('Matière', 'Subject') },
        ]}
        title={`${subject?.name ?? ui('Matière', 'Subject')} · ${c.name}`}
        description={subject ? `Coefficient ${subject.pivot.coefficient}` : ''}
        actions={<Button variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />}
          onClick={() => router.push(`${gradesBase}/${c.id}?term=${termId}`)}>{ui('Retour à la classe', 'Back to class')}</Button>}
      />

      {/* Assessment tabs */}
      <Card className="mb-4">
        <CardContent className="flex flex-wrap items-center gap-2 py-3">
          {(list ?? []).length === 0 ? (
            <p className="text-sm text-secondary-500">{ui('Aucune évaluation pour cette matière. Créez-en une depuis la page de la classe.', 'No assessments yet for this subject. Create one from the class page.')}</p>
          ) : (
            (list ?? []).map((a) => (
              <button key={a.id} onClick={() => setSelectedId(a.id)}
                className={`rounded-button border px-3 py-1.5 text-sm ${selectedId === a.id ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-secondary-200 text-secondary-600 hover:bg-secondary-50'}`}>
                {a.title}
                <span className="ml-2 text-xs opacity-70">/{a.max_score}</span>
              </button>
            ))
          )}
        </CardContent>
      </Card>

      {assessment && (
        <Card>
          <CardContent className="py-4">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="font-semibold text-ink">{assessment.title}</p>
                <p className="text-xs text-secondary-500">
                  {assessment.date} · {assessment.type} · /{assessment.max_score} · {ui('coefficient', 'weight')} {assessment.weight}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge variant={assessment.status === 'published' ? 'success' : 'warning'}>{assessment.status}</Badge>
                <span className="text-xs text-secondary-500" aria-live="polite">
                  {record.isPending
                    ? ui('Enregistrement…', 'Saving…')
                    : isDirty
                      ? ui('Modifications non enregistrées', 'Unsaved changes')
                      : lastSavedAt
                        ? `${ui('Enregistré à', 'Saved')} ${lastSavedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                        : ui('Toutes les modifications sont enregistrées', 'All changes saved')}
                </span>
                <Button size="sm" leftIcon={<Save className="h-4 w-4" />} isLoading={record.isPending} onClick={() => void submit()}>{ui('Enregistrer', 'Save')}</Button>
                {assessment.status === 'draft' && (
                  <Button size="sm" variant="outline" leftIcon={<Send className="h-4 w-4" />} onClick={onPublish}>{ui('Publier', 'Publish')}</Button>
                )}
                <Button size="sm" variant="danger" leftIcon={<Trash2 className="h-4 w-4" />} onClick={onDelete}>{ui('Supprimer', 'Delete')}</Button>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-secondary-200 text-left text-xs uppercase tracking-wider text-secondary-500">
                  <tr>
                    <th className="px-2 py-2">{ui('Élève', 'Student')}</th>
                    <th className="w-32 px-2 py-2 text-right">{ui('Note', 'Score')}</th>
                    <th className="px-2 py-2">{ui('Commentaire', 'Comment')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-secondary-100">
                  {(assessment.entries ?? []).map((e, rowIndex, entries) => {
                    const rawScore = scores[e.student_id] ?? '';
                    const numericScore = rawScore === '' ? null : Number(rawScore);
                    const scoreInvalid = numericScore !== null
                      && (Number.isNaN(numericScore) || numericScore < 0 || numericScore > assessment.max_score);
                    return (
                    <tr key={e.id}>
                      <td className="sticky left-0 bg-surface px-2 py-2">
                        <div className="flex items-center gap-2">
                          {e.student?.photo_url
                            ? <img src={e.student.photo_url} alt="" className="h-7 w-7 rounded-full object-cover" />
                            : e.student && <Avatar name={e.student.full_name} size="sm" />}
                          <div>
                            <p className="font-medium text-ink">{e.student?.full_name}</p>
                            <p className="text-xs text-secondary-500">{e.student?.admission_number}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-2 py-2 text-right">
                        <input
                          type="number" min={0} max={assessment.max_score} step="0.25"
                          inputMode="decimal"
                          data-grade-row={rowIndex}
                          data-grade-column="score"
                          aria-label={`${ui('Note de', 'Score for')} ${e.student?.full_name ?? ui('l’élève', 'student')}`}
                          aria-invalid={scoreInvalid}
                          value={rawScore}
                          onKeyDown={(event) => navigateGrid(event, rowIndex, 'score', entries.length)}
                          onChange={(ev) => {
                            setScores((current) => ({ ...current, [e.student_id]: ev.target.value }));
                            setIsDirty(true);
                          }}
                          className={`h-10 w-24 rounded-input border bg-surface px-2 text-right text-sm focus:ring-2 focus:ring-primary-300 ${
                            scoreInvalid ? 'border-danger bg-danger-light' : 'border-secondary-300'
                          }`}
                        />
                      </td>
                      <td className="px-2 py-2">
                        <input
                          type="text" maxLength={300}
                          data-grade-row={rowIndex}
                          data-grade-column="comment"
                          aria-label={`${ui('Commentaire pour', 'Comment for')} ${e.student?.full_name ?? ui('l’élève', 'student')}`}
                          value={comments[e.student_id] ?? ''}
                          onKeyDown={(event) => navigateGrid(event, rowIndex, 'comment', entries.length)}
                          onChange={(ev) => {
                            setComments((current) => ({ ...current, [e.student_id]: ev.target.value }));
                            setIsDirty(true);
                          }}
                          placeholder={ui('Commentaire facultatif', 'Optional comment')}
                          className="h-10 w-full min-w-56 rounded-input border border-secondary-300 bg-surface px-3 text-sm focus:ring-2 focus:ring-primary-300"
                        />
                      </td>
                    </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
