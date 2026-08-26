import { Badge } from '@/components/ui/Badge';
import type { TeacherStatus } from '@/types/teacher';

const map: Record<TeacherStatus, { variant: 'success' | 'warning' | 'secondary' | 'danger'; label: string }> = {
  active:     { variant: 'success',   label: 'Active' },
  on_leave:   { variant: 'warning',   label: 'On leave' },
  archived:   { variant: 'secondary', label: 'Archived' },
  terminated: { variant: 'danger',    label: 'Terminated' },
};

export function TeacherStatusBadge({ status }: { status: TeacherStatus }) {
  const c = map[status];
  return <Badge variant={c.variant}>{c.label}</Badge>;
}
