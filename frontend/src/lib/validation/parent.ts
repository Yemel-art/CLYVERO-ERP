import { z } from 'zod';

export const parentSchema = z.object({
  first_name:      z.string().min(1, 'First name is required.').max(80),
  last_name:       z.string().min(1, 'Last name is required.').max(80),
  middle_name:     z.string().max(80).optional().or(z.literal('')),
  gender:          z.enum(['male', 'female']),
  email:           z.string().min(1, 'Email is required.').email().max(120),
  phone:           z.string().min(1, 'Phone is required.').max(30),
  alternate_phone: z.string().max(30).optional().or(z.literal('')),
  address:         z.string().max(500).optional().or(z.literal('')),
  city:            z.string().max(80).optional().or(z.literal('')),
  country:         z.string().max(80).optional().or(z.literal('')),
  occupation:      z.string().max(120).optional().or(z.literal('')),
  workplace:       z.string().max(160).optional().or(z.literal('')),
  national_id:     z.string().max(60).optional().or(z.literal('')),
  is_active:       z.boolean().optional(),
  create_user_account: z.boolean().optional(),
});

export type ParentFormValues = z.infer<typeof parentSchema>;

export function cleanParentPayload(v: ParentFormValues): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, val] of Object.entries(v)) {
    if (val === '' || val === undefined || val === null) continue;
    out[k] = val;
  }
  return out;
}
