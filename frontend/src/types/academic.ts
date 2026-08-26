export interface AcademicYear {
  id: string;
  title: string;
  start_date: string;
  end_date: string;
  status: 'upcoming' | 'active' | 'archived';
  school_id: string;
  created_at: string | null;
  terms_count?: number;
  classes_count?: number;
}

export interface Term {
  id: string;
  academic_year_id: string;
  name: string;
  sequence: number;
  start_date: string;
  end_date: string;
  status: 'upcoming' | 'active' | 'closed';
  academic_year?: { id: string; title: string };
}

export interface Subject {
  id: string;
  name: string;
  code: string;
  education_system: 'both' | 'secondary_general' | 'secondary_technical';
  coefficient: number;
  color: string;
  description: string | null;
  is_active: boolean;
  archived_at: string | null;
}

export interface ClassSubjectPivot {
  teacher_id: string | null;
  coefficient: number;
  weekly_frequency: number;
}

export interface ClassSubjectLink {
  id: string;
  name: string;
  code: string;
  color: string;
  pivot: ClassSubjectPivot;
}

export interface SchoolClass {
  id: string;
    education_system: 'secondary_general' | 'secondary_technical';
    name: string;
    grade_level: string;
    speciality: string | null;
    speciality_label: string | null;
    cycle: 'first_cycle' | 'second_cycle' | null;
    cycle_label: string | null;
  language: 'fr' | 'en';
  capacity: number;
  description: string | null;
  is_active: boolean;
  academic_year_id: string;
  form_master_id: string | null;
  academic_year?: { id: string; title: string };
  form_master?: { id: string; full_name: string } | null;
    subjects?: ClassSubjectLink[];
    students?: Array<{
      id: string;
      admission_number: string;
      official_matricule: string | null;
      full_name: string;
      import_name: string;
      date_of_birth: string | null;
      place_of_birth: string | null;
      photo_url: string | null;
      gender: 'male' | 'female';
      status: 'active' | 'archived' | 'graduated' | 'withdrawn';
      status_label: string;
    }>;
  students_count?: number;
  archived_at: string | null;
}

export interface PromotionPolicy {
  id: string;
  academic_year_id: string | null;
  name: string;
  grading_scale: number;
  passing_average: number;
  minimum_subject_mark: number;
  maximum_failed_subjects: number;
  critical_subject_ids: string[];
  failure_conditions: {
    match?: 'all' | 'any';
    average_below?: number;
    failed_subjects_at_least?: number;
    critical_failures_at_least?: number;
  };
  exclusion_conditions: {
    match?: 'all' | 'any';
    average_below?: number;
    failed_subjects_at_least?: number;
    critical_failures_at_least?: number;
  };
  allow_class_council_override: boolean;
  is_active: boolean;
}

export interface AcademicDecision {
  id: string;
  student_id: string;
  student?: { id: string; full_name: string; admission_number: string };
  class?: { id: string; name: string } | null;
  academic_year_id: string;
  computed_decision: 'promoted' | 'repeating' | 'excluded';
  final_decision: 'promoted' | 'repeating' | 'excluded';
  decision_label: string;
  final_average: number | null;
  failed_subjects_count: number;
  failed_subject_ids: string[];
  decision_reasons: string[];
  teacher_appreciation: string | null;
  class_council_recommendation: string | null;
  override_reason: string | null;
  overridden_at: string | null;
  finalized_at: string | null;
}
