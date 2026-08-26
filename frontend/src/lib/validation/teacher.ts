import { z } from 'zod';

export const teacherSchema = z.object({
  first_name:      z.string().min(1, 'First name is required.').max(80),
  last_name:       z.string().min(1, 'Last name is required.').max(80),
  middle_name:     z.string().max(80).optional().or(z.literal('')),
  gender:          z.enum(['male', 'female']),
  email:           z.string().min(1, 'Email is required.').email().max(120),
  phone:           z.string().max(30).optional().or(z.literal('')),
  date_of_birth:   z.string().optional().or(z.literal('')),
  nationality:     z.string().max(80).optional().or(z.literal('')),
  address:         z.string().max(500).optional().or(z.literal('')),
  city:            z.string().max(80).optional().or(z.literal('')),
  country:         z.string().max(80).optional().or(z.literal('')),
  qualification:   z.string().max(120).optional().or(z.literal('')),
  specialization:  z.string().max(120).optional().or(z.literal('')),
  position:        z.string().max(120).optional().or(z.literal('')),
  department:      z.string().max(120).optional().or(z.literal('')),
  years_of_experience: z.coerce.number().int().min(0).max(60).optional(),
  hire_date:       z.string().min(1, 'Hire date is required.'),
  salary:          z.coerce.number().min(0).optional(),
  emergency_contact_name:  z.string().max(120).optional().or(z.literal('')),
  emergency_contact_phone: z.string().max(30).optional().or(z.literal('')),
  create_user_account: z.boolean().optional(),
});

export type TeacherFormValues = z.infer<typeof teacherSchema>;

export function cleanTeacherPayload(v: TeacherFormValues): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, val] of Object.entries(v)) {
    if (val === '' || val === undefined || val === null) continue;
    out[k] = val;
  }
  return out;
}
