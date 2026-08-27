'use client';

import { useState } from 'react';
import { useParams, useSearchParams, useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Plus, ArrowRight, ArrowLeft, Trophy, FileText, Pencil } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { Skeleton } from '@/components/ui/Skeleton';
import { Badge } from '@/components/ui/Badge';
import { Avatar } from '@/components/ui/Avatar';
import { useClass, useTerms } from '@/hooks/academic';
import { useAssessments, useCreateGradeSheet, useClassRanking, useGradeSheets, useUpdateGradeSheet } from '@/hooks/grades';
import type { GradeSheet } from '@/lib/api/grades';
import type { AssessmentType } from '@/types/grades';
import { useAuthStore } from '@/store/auth';
import { useTeacherDashboard } from '@/hooks/dashboard';

function apiErrorMessage(error: any, fallback: string): string {
  const validation = error?.response?.data?.errors as Record<string, string[]> | undefined;
  const firstValidation = validation ? Object.values(validation).flat().find(Boolean) : undefined;
  const candidate = firstValidation ?? error?.response?.data?.message ?? error?.message;
  return typeof candidate === 'string' && candidate.trim() ? candidate : fallback;
}

const TYPES: { value: AssessmentType; label: string }[] = [
  { value: 'sequence', label: 'Sequence' },
  { value: 'quiz', label: 'Quiz' },
  { value: 'test', label: 'Test' },
  { value: 'exam', label: 'Exam' },
  { value: 'project', label: 'Project' },
  { value: 'homework', label: 'Homework' },
  { value: 'other', label: 'Other' },
];

export default function ClassGradebookPage() {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const gradesBase = user?.role.name === 'teacher' ? '/teacher/grades' : '/admin/grades';
  const dashboardBase = user?.role.name === 'teacher' ? '/teacher/dashboard' : '/admin/dashboard';
  const isTeacher = user?.role.name === 'teacher';
  const { data: teacherDashboard } = useTeacherDashboard(isTeacher);
  const { classId } = useParams<{ classId: string }>();
  const search = useSearchParams();
  const requestedTermId = search.get('term') ?? undefined;

  const { data: c, isLoading } = useClass(classId);
  const { data: terms } = useTerms(c?.academic_year_id);
  const activeTerm = terms?.find((term) => term.status === 'active') ?? terms?.[0];
  const termId = requestedTermId ?? activeTerm?.id;
  const { data: assessments } = useAssessments({ class_id: classId, term_id: termId }, false);
  const { data: gradeSheets } = useGradeSheets(classId, termId);
  const createSheet = useCreateGradeSheet();
  const updateSheet = useUpdateGradeSheet();
  const { data: ranking } = useClassRanking(classId, termId, false);

  const [open, setOpen] = useState(false);
  const [subjectId, setSubjectId] = useState('');
  const [title, setTitle] = useState('');
  const [type, setType] = useState<AssessmentType>('sequence');
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [maxScore, setMaxScore] = useState(20);
  const [weight, setWeight] = useState(1);
  const [sheetScores, setSheetScores] = useState<Record<string, Record<string, string>>>({});
  const [editingSheet, setEditingSheet] = useState<GradeSheet | null>(null);
  const [editScores, setEditScores] = useState<Record<string, Record<string, string>>>({});

  if (isLoading || !c) return <div className="space-y-4"><Skeleton className="h-8 w-1/3" /><Skeleton className="h-64" /></div>;
  const students = c.students ?? [];
  const visibleSubjects = isTeacher
    ? (c.subjects ?? []).filter((subject) => teacherDashboard?.teaching.some(
        (assignment) => assignment.class_id === classId && assignment.subject_id === subject.id,
      ))
    : (c.subjects ?? []);
  const assessmentSubjects = visibleSubjects;

  const submit = async () => {
    if (!termId) { toast.error('No term selected.'); return; }
    if (students.length === 0) { toast.error('This class has no students.'); return; }
    if (students.some((student) => assessmentSubjects.some(
      (subject) => (sheetScores[student.id]?.[subject.id] ?? '').trim() === '',
    ))) {
      toast.error('Enter every student’s mark for every subject before saving.');
      return;
    }
    try {
      await createSheet.mutateAsync({
        term_id: termId,
        class_id: classId,
        title,
        type,
        date,
        max_score: maxScore,
        weight,
        subjects: assessmentSubjects.map((subject) => ({
          subject_id: subject.id,
          entries: students.map((student) => ({
            student_id: student.id,
            score: Number(sheetScores[student.id][subject.id]),
          })),
        })),
      });
      toast.success('Complete grade sheet saved.');
      setOpen(false);
      setSheetScores({});
    } catch (err: any) {
      toast.error(apiErrorMessage(err, 'Could not save the grade sheet.'));
    }
  };

  const openSheet = (sheet: GradeSheet) => {
    const values: Record<string, Record<string, string>> = {};
    sheet.students.forEach((student) => {
      values[student.id] = {};
      sheet.subjects.forEach((subject) => {
        values[student.id][subject.id] = student.scores[subject.id] === null
          || student.scores[subject.id] === undefined ? '' : String(student.scores[subject.id]);
      });
    });
    setEditScores(values);
    setEditingSheet(sheet);
  };

  const saveEditedSheet = async () => {
    if (!editingSheet) return;
    const missing = editingSheet.students.some((student) => editingSheet.subjects.some(
      (subject) => (editScores[student.id]?.[subject.id] ?? '').trim() === '',
    ));
    if (missing) { toast.error('Complete every mark before saving.'); return; }
    try {
      await updateSheet.mutateAsync({
        sheetId: editingSheet.id,
        scores: editingSheet.subjects.flatMap((subject) => editingSheet.students.map((student) => ({
          assessment_id: subject.assessment_id,
          student_id: student.id,
          score: Number(editScores[student.id][subject.id]),
        }))),
      });
      toast.success('Grade corrections saved.');
      setEditingSheet(null);
    } catch (error: any) {
      toast.error(apiErrorMessage(error, 'Could not update the grade sheet.'));
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[
          { label: 'Home', href: dashboardBase },
          { label: 'Grades', href: gradesBase },
          { label: c.name },
        ]}
        title={`Gradebook · ${c.name}`}
        description={`${c.grade_level} · ${c.academic_year?.title ?? ''}`}
        actions={<>
          <Button variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />} onClick={() => router.push(gradesBase)}>Back</Button>
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setOpen(true)}
            disabled={!termId || assessmentSubjects.length === 0}>
            Enter grade sheet
          </Button>
        </>}
      />

      <Card className="mb-6">
        <CardHeader>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <CardTitle className="text-base">Students in {c.name}</CardTitle>
              <CardDescription>
                {students.length} student(s). Create or open a grade sheet below to enter their marks.
              </CardDescription>
            </div>
            {(terms ?? []).length > 0 && (
              <select
                value={termId ?? ''}
                onChange={(event) => router.push(`${gradesBase}/${classId}?term=${event.target.value}`)}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm"
              >
                {(terms ?? []).map((term) => (
                  <option key={term.id} value={term.id}>{term.name} ({term.status})</option>
                ))}
              </select>
            )}
          </div>
        </CardHeader>
        <CardContent>
          {students.length === 0 ? (
            <p className="text-sm text-warning">No students are assigned to this class.</p>
          ) : (
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {students.map((student) => (
                <div key={student.id} className="flex items-center gap-3 rounded-card border border-secondary-100 p-3">
                  <Avatar name={student.full_name} src={student.photo_url} size="sm" />
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-ink">{student.full_name}</p>
                    <p className="text-xs text-secondary-500">{student.admission_number}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
          {(terms ?? []).length === 0 && !termId && (
            <div className="mt-4 rounded-card border border-warning/30 bg-warning-light p-3 text-sm text-warning-dark">
              No term exists for this academic year. Create Term 1, Term 2, and Term 3 under Academic → Terms before recording grades.
            </div>
          )}
        </CardContent>
      </Card>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle className="text-base">Saved grade sheets</CardTitle>
          <CardDescription>One compact record per assessment. Open a sheet to edit every student and subject together.</CardDescription>
        </CardHeader>
        <CardContent>
          {(gradeSheets ?? []).length === 0 ? (
            <p className="py-6 text-center text-sm text-secondary-500">No complete grade sheet has been saved for this term.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[42rem] text-sm">
                <thead><tr className="border-b text-left text-xs uppercase text-secondary-500">
                  <th className="px-2 py-2">Assessment</th><th>Type</th><th>Date</th>
                  <th>Students</th><th>Subjects</th><th>Status</th><th />
                </tr></thead>
                <tbody className="divide-y divide-secondary-100">
                  {(gradeSheets ?? []).map((sheet) => (
                    <tr key={sheet.id}>
                      <td className="px-2 py-3 font-medium text-ink">{sheet.title}</td>
                      <td className="capitalize">{sheet.type}</td><td>{sheet.date}</td>
                      <td>{sheet.students.length}</td><td>{sheet.subjects.length}</td>
                      <td><Badge variant={sheet.status === 'published' ? 'success' : 'warning'}>{sheet.status}</Badge></td>
                      <td className="text-right"><Button size="sm" variant="outline"
                        leftIcon={<Pencil className="h-3 w-3" />} onClick={() => openSheet(sheet)}>Open & edit</Button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <div className="hidden">
        {/* Subjects + assessments */}
        <div className="space-y-4 lg:col-span-2">
          {visibleSubjects.length === 0 ? (
            <Card><CardContent className="py-12 text-center text-sm text-secondary-500">
              This class has no subjects attached yet. <a href={`/admin/academic/classes/${c.id}`} className="text-primary-600">Add subjects →</a>
            </CardContent></Card>
          ) : (
            visibleSubjects.map((s) => {
              const subjectAssessments = (assessments ?? []).filter((a) => a.subject_id === s.id);
              return (
                <Card key={s.id}>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <div className="h-3 w-3 rounded-full" style={{ backgroundColor: s.color }} />
                        <CardTitle className="text-base">{s.name}</CardTitle>
                        <Badge variant="secondary">Coeff {s.pivot.coefficient}</Badge>
                      </div>
                      <Button size="sm" variant="outline"
                        onClick={() => router.push(`${gradesBase}/${c.id}/${s.id}?term=${termId}`)}>
                        Open subject <ArrowRight className="ml-1 h-3 w-3" />
                      </Button>
                    </div>
                  </CardHeader>
                  <CardContent>
                    {subjectAssessments.length === 0 ? (
                      <p className="py-4 text-center text-sm text-secondary-500">No assessments yet.</p>
                    ) : (
                      <ul className="divide-y divide-secondary-100">
                        {subjectAssessments.slice(0, 4).map((a) => (
                          <li key={a.id} className="flex items-center justify-between py-2">
                            <div className="flex items-center gap-2">
                              <FileText className="h-4 w-4 text-secondary-400" />
                              <div>
                                <p className="text-sm font-medium text-ink">{a.title}</p>
                                <p className="text-xs text-secondary-500">{a.date} · {a.type} · /{a.max_score}</p>
                              </div>
                            </div>
                            <Badge variant={a.status === 'published' ? 'success' : 'warning'}>{a.status}</Badge>
                          </li>
                        ))}
                      </ul>
                    )}
                  </CardContent>
                </Card>
              );
            })
          )}
        </div>

        {/* Ranking widget */}
        <div>
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <Trophy className="h-4 w-4 text-warning" />
                <CardTitle className="text-base">Class ranking</CardTitle>
              </div>
              <CardDescription>Based on published assessments in this term.</CardDescription>
            </CardHeader>
            <CardContent>
              {!ranking ? <Skeleton className="h-32" /> : ranking.ranking.length === 0 ? (
                <p className="text-sm text-secondary-500">No grades published yet.</p>
              ) : (
                <ol className="space-y-2">
                  {ranking.ranking.slice(0, 10).map((r) => (
                    <li key={r.student.id} className="flex items-center gap-3 rounded-card border border-secondary-100 p-2 text-sm">
                      <span className="w-6 text-center font-semibold text-secondary-500">#{r.rank}</span>
                      {r.student.photo_url
                        ? <img src={r.student.photo_url} alt="" className="h-8 w-8 rounded-full object-cover" />
                        : <Avatar name={r.student.full_name} size="sm" />}
                      <span className="flex-1 truncate">{r.student.full_name}</span>
                      <span className="font-semibold text-ink">{r.average !== null ? `${r.average}/20` : '—'}</span>
                    </li>
                  ))}
                </ol>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      <Modal open={editingSheet !== null} onClose={() => setEditingSheet(null)}
        title={editingSheet ? `${editingSheet.title} · ${c.name}` : 'Edit grade sheet'} size="xl"
        description="Each student stays on one row. Correct any mark and save the whole sheet once."
        closeOnBackdrop={false}
        footer={<>
          <Button variant="outline" onClick={() => setEditingSheet(null)}>Cancel</Button>
          <Button onClick={saveEditedSheet} isLoading={updateSheet.isPending}>Save corrections</Button>
        </>}>
        {editingSheet && (
          <div className="overflow-x-auto rounded-card border border-secondary-200">
            <table className="w-full min-w-max text-sm">
              <thead className="sticky top-0 bg-secondary-50 text-xs text-secondary-600">
                <tr>
                  <th className="sticky left-0 z-10 min-w-52 bg-secondary-50 px-3 py-3 text-left">Student</th>
                  {editingSheet.subjects.map((subject) => (
                    <th key={subject.id} className="min-w-28 px-2 py-3 text-center">
                      {subject.name}<span className="block font-normal">/{editingSheet.max_score}</span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-secondary-100">
                {editingSheet.students.map((student) => (
                  <tr key={student.id}>
                    <td className="sticky left-0 bg-surface px-3 py-2">
                      <p className="font-medium text-ink">{student.full_name}</p>
                      <p className="text-xs text-secondary-500">{student.admission_number}</p>
                    </td>
                    {editingSheet.subjects.map((subject) => (
                      <td key={subject.id} className="px-2 py-2">
                        <input type="number" min={0} max={editingSheet.max_score} step={0.25}
                          value={editScores[student.id]?.[subject.id] ?? ''}
                          onChange={(event) => setEditScores((current) => ({
                            ...current,
                            [student.id]: { ...current[student.id], [subject.id]: event.target.value },
                          }))}
                          className="h-10 w-24 rounded-input border border-secondary-300 px-2 text-center focus:border-primary-500 focus:ring-2 focus:ring-primary-200" />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Modal>

      <Modal open={open} onClose={() => setOpen(false)} title={`Enter grades · ${c.name}`} size="xl"
        description="Choose the assessment once, enter every subject mark, then save the complete sheet."
        closeOnBackdrop={false}
        footer={<>
          <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
          <Button onClick={submit} isLoading={createSheet.isPending}
            disabled={!title || !maxScore || assessmentSubjects.length === 0 || students.length === 0}>
            Save complete grade sheet
          </Button>
        </>}>
        <div className="space-y-4">
          {false && (
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-secondary-700">Subject</label>
            <select value={subjectId} onChange={(e) => setSubjectId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">Select subject</option>
              {assessmentSubjects.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
          </div>
          )}
          <Input label="Assessment title" placeholder="e.g. First sequence" value={title} onChange={(e) => setTitle(e.target.value)} />
          <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-secondary-700">Type</label>
              <select value={type} onChange={(e) => setType(e.target.value as AssessmentType)}
                className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </div>
            <Input type="date" label="Date" value={date} onChange={(e) => setDate(e.target.value)} />
            <Input type="number" label="Max score" min={1} step={0.5} value={maxScore} onChange={(e) => setMaxScore(Number(e.target.value))} />
            <Input type="number" label="Weight" min={0.1} step={0.1} value={weight} onChange={(e) => setWeight(Number(e.target.value))} />
          </div>
          <div className="overflow-x-auto rounded-card border border-secondary-200">
            <table className="w-full min-w-max text-sm">
              <thead className="sticky top-0 bg-secondary-50 text-left text-xs text-secondary-600">
                <tr>
                  <th className="sticky left-0 z-10 min-w-52 bg-secondary-50 px-3 py-3">Student</th>
                  {assessmentSubjects.map((subject) => (
                    <th key={subject.id} className="min-w-28 px-2 py-3 text-center">
                      <span className="block font-semibold text-ink">{subject.name}</span>
                      <span className="font-normal">/{maxScore}</span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-secondary-100">
                {students.map((student) => (
                  <tr key={student.id}>
                    <td className="sticky left-0 bg-surface px-3 py-2">
                      <p className="font-medium text-ink">{student.full_name}</p>
                      <p className="text-xs text-secondary-500">{student.admission_number}</p>
                    </td>
                    {assessmentSubjects.map((subject) => (
                      <td key={subject.id} className="px-2 py-2">
                        <input type="number" min={0} max={maxScore} step={0.25}
                          aria-label={`${subject.name} score for ${student.full_name}`}
                          value={sheetScores[student.id]?.[subject.id] ?? ''}
                          onChange={(event) => setSheetScores((current) => ({
                            ...current,
                            [student.id]: { ...current[student.id], [subject.id]: event.target.value },
                          }))}
                          className="h-10 w-24 rounded-input border border-secondary-300 px-2 text-center focus:border-primary-500 focus:ring-2 focus:ring-primary-200" />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </Modal>
    </div>
  );
}
