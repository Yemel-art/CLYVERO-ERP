import { apiClient, unwrap } from './client';
import type { ApiResponse } from '@/types/api';

export interface AdminDashboard {
  active_year: { id: string; title: string } | null;
  active_term: { id: string; name: string } | null;
  counts: { students: number; teachers: number; parents: number; classes: number };
  attendance_today: { present: number; absent: number; late: number; excused: number };
  finance: { billed: number; collected: number; outstanding: number; overdue: number };
  recent_payments: Array<{
    id: string; receipt_number: string; paid_at: string; amount: number; method: string;
    student: { id: string; full_name: string; admission_number: string } | null;
  }>;
}

export interface SecretaryDashboard {
  enrollments: { this_week: number; this_month: number; archived: number };
  pending_invoices: number;
  overdue_invoices: number;
  recent_students: Array<{ id: string; full_name: string; admission_number: string; created_at: string }>;
}

export interface TeacherDashboard {
  teacher: { id: string; full_name: string; employee_number: string } | null;
  form_master_classes: Array<{
    id: string;
    name: string;
    grade_level: string;
    speciality: string | null;
    cycle: string | null;
    language: 'fr' | 'en';
    academic_year: string | null;
    students_count: number;
  }>;
  teaching: Array<{
    class_id: string;
    class_name: string;
    grade_level: string;
    speciality: string | null;
    cycle: string | null;
    language: 'fr' | 'en';
    academic_year: string | null;
    subject_id: string;
    subject_name: string;
    subject_color: string;
    coefficient: number;
    weekly_frequency: number | null;
  }>;
}

export interface ParentDashboard {
  parent: { id: string; full_name: string };
  children: Array<{
    id: string; full_name: string; admission_number: string;
    photo_url: string | null; class: string | null; balance: number;
  }>;
}

export const dashboardApi = {
  async admin() {
    const { data } = await apiClient.get<ApiResponse<AdminDashboard>>('/dashboard/admin');
    return unwrap(data);
  },
  async secretary() {
    const { data } = await apiClient.get<ApiResponse<SecretaryDashboard>>('/dashboard/secretary');
    return unwrap(data);
  },
  async teacher() {
    const { data } = await apiClient.get<ApiResponse<TeacherDashboard>>('/dashboard/teacher');
    return unwrap(data);
  },
  async parent() {
    const { data } = await apiClient.get<ApiResponse<ParentDashboard>>('/dashboard/parent');
    return unwrap(data);
  },
};
