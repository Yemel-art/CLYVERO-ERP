import type { Gender } from './student';

export type TeacherStatus = 'active' | 'on_leave' | 'archived' | 'terminated';

export interface Teacher {
  id: string;
  employee_number: string;
  first_name: string;
  last_name: string;
  middle_name: string | null;
  full_name: string;
  gender: Gender;
  date_of_birth: string | null;
  nationality: string | null;
  photo_url: string | null;

  email: string;
  phone: string | null;
  address: string | null;
  city: string | null;
  country: string | null;

  qualification: string | null;
  specialization: string | null;
  position: string | null;
  department: string | null;
  years_of_experience: number;
  hire_date: string;
  salary?: number | null;

  emergency_contact: { name: string | null; phone: string | null };

  status: TeacherStatus;
  status_label: string;

  user?: { id: string; email: string; is_active: boolean; last_login_at: string | null } | null;
  teaching_assignments?: Array<{
    class_id: string;
    class_name: string;
    grade_level: string;
    speciality: string | null;
    cycle: string | null;
    language: 'fr' | 'en';
    subject_id: string;
    subject_name: string;
    coefficient: number;
    weekly_frequency: number | null;
    academic_year: string | null;
  }>;
  form_master_classes?: Array<{
    id: string;
    name: string;
    grade_level: string;
    speciality: string | null;
    cycle: string | null;
    language: 'fr' | 'en';
    academic_year: string | null;
  }>;

  created_at: string | null;
  archived_at: string | null;
}

export interface CreateTeacherResult {
  teacher: Teacher;
  login_credentials: {
    email: string;
    temporary_password: string;
  } | null;
}

export interface TeacherStatistics {
  total: number;
  on_leave: number;
  archived: number;
  terminated: number;
}

export interface TeacherFilters {
  q?: string;
  status?: TeacherStatus;
  gender?: Gender;
  include_archived?: boolean;
  page?: number;
  per_page?: number;
  sort?: string;
  order?: 'asc' | 'desc';
}

export const TEACHER_STATUS_OPTIONS: { value: TeacherStatus; label: string }[] = [
  { value: 'active',     label: 'Active' },
  { value: 'on_leave',   label: 'On leave' },
  { value: 'archived',   label: 'Archived' },
  { value: 'terminated', label: 'Terminated' },
];
