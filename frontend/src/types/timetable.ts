export type DayOfWeek = 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday';

export interface TimetableSlot {
  id: string;
  class_id: string;
  subject_id: string | null;
  teacher_id: string | null;
  day_of_week: DayOfWeek;
  start_time: string;
  end_time: string;
  room: string | null;
  notes: string | null;
  subject?: { id: string; name: string; code: string; color: string } | null;
  teacher?: { id: string; full_name: string } | null;
  class?: { id: string; name: string } | null;
}

export interface TimetablePeriod {
  label?: string;
  start_time: string;
  end_time: string;
}

export interface TimetableAvailability {
  teacher_id: string;
  day_of_week: DayOfWeek;
  period_index: number;
  is_available: boolean;
}

export interface TimetableGenerationConfig {
  academic_year_id: string;
  working_days: DayOfWeek[];
  periods: TimetablePeriod[];
  break_periods: number[];
}

export interface TimetableGenerationData {
  config: TimetableGenerationConfig;
  classes: Array<{
    id: string;
    name: string;
    subjects: Array<{
      id: string;
      name: string;
      teacher_id: string | null;
      weekly_frequency: number;
    }>;
  }>;
  availability: TimetableAvailability[];
}

export interface TimetableGenerationResult {
  generated: boolean;
  slots: number;
  conflicts: string[];
}

export const DAYS: { value: DayOfWeek; label: string }[] = [
  { value: 'monday', label: 'Mon' },
  { value: 'tuesday', label: 'Tue' },
  { value: 'wednesday', label: 'Wed' },
  { value: 'thursday', label: 'Thu' },
  { value: 'friday', label: 'Fri' },
  { value: 'saturday', label: 'Sat' },
];
