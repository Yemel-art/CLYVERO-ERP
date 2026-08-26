/**
 * Student-related types. Mirror App\Http\Resources\StudentResource.
 */

export type Gender = 'male' | 'female';

export type Cycle = 'secondary_general' | 'secondary_technical';

export type StudentStatus = 'active' | 'archived' | 'graduated' | 'withdrawn' | 'excluded';

export interface StudentAcademicHistory {
  id: string;
  academic_year: { id: string; title: string; status: string };
  class: { id: string; name: string; grade_level: string } | null;
  enrolled_at: string;
  status: 'active' | 'completed' | 'repeating' | 'excluded' | 'graduated' | 'withdrawn';
  decision: {
    status: 'promoted' | 'repeating' | 'excluded';
    label: string;
    final_average: string | null;
    teacher_appreciation: string | null;
    class_council_recommendation: string | null;
    finalized_at: string | null;
  } | null;
}

export interface StudentEmergencyContact {
  name: string | null;
  phone: string | null;
  relationship: string | null;
}

export interface StudentHealth {
  blood_group: string | null;
  allergies: string | null;
  medical_conditions: string | null;
}

export interface Student {
  id: string;
  admission_number: string;
  official_matricule: string | null;
  first_name: string;
  last_name: string;
  middle_name: string | null;
  full_name: string;
  initials: string;
  gender: Gender | null;
  date_of_birth: string | null;       // YYYY-MM-DD when known
  age: number | null;
  place_of_birth: string | null;
  nationality: string | null;
  religion: string | null;
  photo_url: string | null;

  email: string | null;
  phone: string | null;
  address: string | null;
  city: string | null;
  country: string | null;

  parent_id: string | null;
  primary_parent?: {
    id: string;
    full_name: string;
    email: string;
    phone: string;
    has_portal: boolean;
  } | null;
  parent_credentials?: {
    email: string;
    temporary_password: string;
  };
  class_id: string | null;
  class?: {
    id: string;
    name: string;
    grade_level: string;
  } | null;
  academic_year_id: string | null;
  enrollment_date: string;
  previous_school: string | null;

  // ─── Cycle & speciality (filière technique) ─────────────────────
  cycle: Cycle;
  cycle_label: string;
  speciality: string | null;
  speciality_label: string | null;

  emergency_contact: StudentEmergencyContact;
  health: StudentHealth;

  status: StudentStatus;
  status_label: string;

  academic_year?: { id: string; title: string };
  created_by?: { id: string; full_name: string } | null;

  created_at: string | null;
  updated_at: string | null;
  archived_at: string | null;
}

export interface StudentImportRow {
  id: string;
  row_number: number;
  normalized_data: Record<string, string | null>;
  status: 'valid' | 'duplicate' | 'error' | 'imported';
  action: 'create' | 'update' | 'skip';
  errors: Record<string, string> | null;
  warnings: Record<string, string> | null;
  student_id: string | null;
  invoice_id: string | null;
  fee_assigned: boolean;
}

export interface StudentImportAnalysis {
  detected_columns: string[];
  suggested_mapping: Record<string, string>;
  supported_fields: Array<{ key: string; label: string; required: boolean }>;
  sample_rows: Array<Record<string, string>>;
  row_count: number;
  active_academic_year: { id: string; title: string };
}

export interface StudentImport {
  id: string;
  academic_year_id: string;
  academic_year?: { id: string; title: string };
  source: 'spreadsheet' | 'cartes_scolaire';
  original_filename: string;
  status: 'previewed' | 'processing' | 'completed' | 'failed';
  detected_columns: string[];
  column_mapping: Record<string, string>;
  class_mapping: Record<string, string>;
  duplicate_action: 'skip' | 'update';
  summary: Record<string, number>;
  external_classes: string[];
  rows: StudentImportRow[];
  completed_at: string | null;
  created_at: string | null;
}

export interface StudentStatistics {
  total: number;
  archived: number;
  graduated: number;
  withdrawn: number;
}

export interface StudentFilters {
  q?: string;
  status?: StudentStatus;
  gender?: Gender;
  cycle?: Cycle;
  class_id?: string;
  academic_year_id?: string;
  include_archived?: boolean;
  page?: number;
  per_page?: number;
  sort?: string;
  order?: 'asc' | 'desc';
}

export const GENDER_OPTIONS: { value: Gender; label: string }[] = [
  { value: 'male',   label: 'Male' },
  { value: 'female', label: 'Female' },
];

export const STATUS_OPTIONS: { value: StudentStatus; label: string }[] = [
  { value: 'active',    label: 'Active' },
  { value: 'archived',  label: 'Archived' },
  { value: 'graduated', label: 'Graduated' },
  { value: 'withdrawn', label: 'Withdrawn' },
  { value: 'excluded',  label: 'Excluded' },
];
