import { apiClient, unwrap } from './client';
import type { ApiResponse } from '@/types/api';
import type {
  TimetableAvailability,
  TimetableGenerationData,
  TimetableGenerationResult,
  TimetablePeriod,
  TimetableSlot,
  DayOfWeek,
} from '@/types/timetable';

export const timetableApi = {
  async classSlots(classId: string) {
    const { data } = await apiClient.get<ApiResponse<TimetableSlot[]>>(`/timetable/classes/${classId}/slots`);
    return unwrap(data);
  },
  async teacherSlots(teacherId: string) {
    const { data } = await apiClient.get<ApiResponse<TimetableSlot[]>>(`/timetable/teachers/${teacherId}/slots`);
    return unwrap(data);
  },
  async mySlots() {
    const { data } = await apiClient.get<ApiResponse<TimetableSlot[]>>('/timetable/my-slots');
    return unwrap(data);
  },
  async schoolSlots(academicYearId: string) {
    const { data } = await apiClient.get<ApiResponse<TimetableSlot[]>>(`/timetable/school/${academicYearId}/slots`);
    return unwrap(data);
  },
  async create(payload: Partial<TimetableSlot>) {
    const { data } = await apiClient.post<ApiResponse<TimetableSlot>>('/timetable/slots', payload);
    return unwrap(data);
  },
  async update(id: string, payload: Partial<TimetableSlot>) {
    const { data } = await apiClient.patch<ApiResponse<TimetableSlot>>(`/timetable/slots/${id}`, payload);
    return unwrap(data);
  },
  async remove(id: string) {
    const { data } = await apiClient.delete<ApiResponse<null>>(`/timetable/slots/${id}`);
    if (!data.success) throw new Error(data.message);
  },
  async generationConfig(academicYearId: string) {
    const { data } = await apiClient.get<ApiResponse<TimetableGenerationData>>(
      `/timetable/generation-config/${academicYearId}`,
    );
    return unwrap(data);
  },
  async saveGenerationConfig(payload: {
    academic_year_id: string;
    working_days: DayOfWeek[];
    periods: TimetablePeriod[];
    break_periods: number[];
    frequencies: Array<{ class_id: string; subject_id: string; weekly_frequency: number }>;
    availability: TimetableAvailability[];
  }) {
    const { data } = await apiClient.put<ApiResponse<TimetableGenerationData['config']>>(
      '/timetable/generation-config',
      payload,
    );
    return unwrap(data);
  },
  async generate(academicYearId: string, replaceExisting: boolean) {
    const { data } = await apiClient.post<ApiResponse<TimetableGenerationResult>>(
      '/timetable/generate',
      { academic_year_id: academicYearId, replace_existing: replaceExisting },
    );
    return unwrap(data);
  },
};
