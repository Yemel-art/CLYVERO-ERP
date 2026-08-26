export type AttendanceStatus = 'present' | 'absent' | 'late' | 'excused';

export interface AttendanceRecord {
  id: string;
  student_id: string;
  status: AttendanceStatus;
  notes: string | null;
  student?: {
    id: string;
    admission_number: string;
    full_name: string;
    photo_url: string | null;
  } | null;
}

export interface AttendanceSession {
  id: string;
  class_id: string;
  term_id: string | null;
  date: string;
  period: string | null;
  status: 'open' | 'closed';
  notes: string | null;
  taken_by?: { id: string; full_name: string } | null;
  records?: AttendanceRecord[];
}

export interface AttendanceStats {
  present: number;
  absent: number;
  late: number;
  excused: number;
}

export interface StudentAttendanceSummary extends AttendanceStats {
  total: number;
  rate: number;
}
