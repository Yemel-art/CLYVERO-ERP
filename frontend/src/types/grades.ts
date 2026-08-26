export type AssessmentType = 'quiz' | 'test' | 'sequence' | 'exam' | 'project' | 'homework' | 'other';
export type AssessmentStatus = 'draft' | 'published';

export interface GradeEntry {
  id: string;
  student_id: string;
  score: number | null;
  grade_letter: string | null;
  comment: string | null;
  graded_at: string | null;
  student?: {
    id: string;
    admission_number: string;
    full_name: string;
    photo_url: string | null;
  } | null;
}

export interface Assessment {
  id: string;
  term_id: string;
  class_id: string;
  subject_id: string;
  teacher_id: string | null;
  title: string;
  type: AssessmentType;
  date: string;
  max_score: number;
  weight: number;
  status: AssessmentStatus;
  subject?: { id: string; name: string; code: string; color: string };
  term?: { id: string; name: string };
  entries?: GradeEntry[];
}

export interface SubjectAverage {
  subject_id: string;
  name: string;
  code: string;
  color: string;
  coefficient: number;
  average: number | null;
  entries: number;
}

export interface TermReport {
  student: { id: string; full_name: string; admission_number: string; photo_url: string | null };
  term: { id: string; name: string };
  report: {
    subjects: SubjectAverage[];
    overall_average: number | null;
    total_coefficient: number;
  };
}

export interface ClassRankingEntry {
  rank: number;
  student: { id: string; full_name: string; admission_number: string; photo_url: string | null };
  average: number | null;
}

export interface ClassRanking {
  class: { id: string; name: string };
  term: { id: string; name: string };
  ranking: ClassRankingEntry[];
}
