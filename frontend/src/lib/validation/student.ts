import { z } from 'zod';
import { CYCLES, type Cycle } from '@/config/student';

const genderSchema = z.enum(['male', 'female'], {
  required_error: 'Please select a gender.',
});
const cycleValues = CYCLES.map((cycle) => cycle.value) as [Cycle, ...Cycle[]];
const earliestDob = new Date('1950-01-01');

export const studentSchema = z.object({
  admission_number: z.string().trim().min(1, 'Student matriculation number is required.')
    .max(40, 'Student matriculation number cannot exceed 40 characters.')
    .regex(/^[A-Za-z0-9][A-Za-z0-9/._-]*$/, 'Use letters, numbers, /, ., _ or - only.'),
  first_name: z.string().min(1, 'First name is required.').max(80),
  last_name: z.string().min(1, 'Last name is required.').max(80),
  middle_name: z.string().max(80).optional().or(z.literal('')),
  gender: genderSchema,
  date_of_birth: z.string().min(1, 'Date of birth is required.')
    .refine((value) => !Number.isNaN(Date.parse(value)), 'Please enter a valid date.')
    .refine((value) => new Date(value) < new Date(), 'Date of birth must be in the past.')
    .refine((value) => new Date(value) > earliestDob, 'Date of birth is too far in the past.'),
  place_of_birth: z.string().max(120).optional().or(z.literal('')),
  nationality: z.string().max(80).optional().or(z.literal('')),
  religion: z.string().max(80).optional().or(z.literal('')),
  email: z.string().email('Please enter a valid email.').max(120).optional().or(z.literal('')),
  phone: z.string().max(30).optional().or(z.literal('')),
  address: z.string().max(500).optional().or(z.literal('')),
  city: z.string().max(80).optional().or(z.literal('')),
  country: z.string().max(80).optional().or(z.literal('')),
  parent_id: z.string().uuid().optional().or(z.literal('')),
  guardian_mode: z.enum(['existing', 'new']).optional(),
  existing_parent_id: z.string().uuid().optional().or(z.literal('')),
  parent_first_name: z.string().max(80).optional().or(z.literal('')),
  parent_last_name: z.string().max(80).optional().or(z.literal('')),
  parent_gender: z.enum(['male', 'female']).optional(),
  parent_email: z.string().email('Please enter a valid parent email.').max(120).optional().or(z.literal('')),
  parent_phone: z.string().max(30).optional().or(z.literal('')),
  parent_relationship: z.string().max(60).optional().or(z.literal('')),
  create_parent_account: z.boolean().optional(),
  class_id: z.string().uuid('Please select a class.'),
  academic_year_id: z.string().uuid('Please select an academic year.'),
  enrollment_date: z.string().min(1, 'Enrollment date is required.')
    .refine((value) => !Number.isNaN(Date.parse(value)), 'Please enter a valid date.')
    .refine((value) => new Date(value) <= new Date(), 'Enrollment date cannot be in the future.'),
  previous_school: z.string().max(160).optional().or(z.literal('')),
  initial_payment: z.coerce.number().min(0).optional(),
  cycle: z.enum(cycleValues, { required_error: 'Please select the student\'s education system.' }),
  speciality: z.string().max(80).optional().or(z.literal('')),
  emergency_contact_name: z.string().max(120).optional().or(z.literal('')),
  emergency_contact_phone: z.string().max(30).optional().or(z.literal('')),
  emergency_contact_relationship: z.string().max(60).optional().or(z.literal('')),
  blood_group: z.string().max(5).optional().or(z.literal('')),
  allergies: z.string().max(1000).optional().or(z.literal('')),
  medical_conditions: z.string().max(1000).optional().or(z.literal('')),
}).superRefine((data, context) => {
  if (data.cycle === 'secondary_technical' && !data.speciality) {
    context.addIssue({
      code: z.ZodIssueCode.custom,
      message: 'Speciality is required for Secondary Technical students.',
      path: ['speciality'],
    });
  }
  if (data.guardian_mode === 'existing' && !data.existing_parent_id) {
    context.addIssue({ code: z.ZodIssueCode.custom, message: 'Please select the student\'s parent or guardian.', path: ['existing_parent_id'] });
  }
  if (data.guardian_mode && !data.parent_relationship) {
    context.addIssue({ code: z.ZodIssueCode.custom, message: 'Relationship is required.', path: ['parent_relationship'] });
  }
  if (data.guardian_mode === 'new') {
    const required: Array<[keyof typeof data, string]> = [
      ['parent_first_name', 'Parent first name is required.'],
      ['parent_last_name', 'Parent last name is required.'],
      ['parent_email', 'Parent email is required.'],
      ['parent_phone', 'Parent phone is required.'],
      ['parent_relationship', 'Relationship is required.'],
    ];
    required.forEach(([field, message]) => {
      if (!data[field]) context.addIssue({ code: z.ZodIssueCode.custom, message, path: [field] });
    });
  }
});

export type StudentFormValues = z.infer<typeof studentSchema>;

export function cleanStudentPayload(values: StudentFormValues): Record<string, unknown> {
  const result: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(values)) {
    if (value === '' || value === undefined) continue;
    result[key] = value;
  }
  return result;
}
