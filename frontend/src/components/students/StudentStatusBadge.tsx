import { Badge } from '@/components/ui/Badge';
import type { StudentStatus } from '@/types/student';

const map: Record<StudentStatus, { variant: 'success' | 'secondary' | 'info' | 'warning'; label: string }> = {
  active:    { variant: 'success',   label: 'Active' },
  archived:  { variant: 'secondary', label: 'Archived' },
  graduated: { variant: 'info',      label: 'Graduated' },
  withdrawn: { variant: 'warning',   label: 'Withdrawn' },
  excluded:  { variant: 'warning',   label: 'Excluded' },
};

export function StudentStatusBadge({ status }: { status: StudentStatus }) {
  const cfg = map[status];
  return <Badge variant={cfg.variant}>{cfg.label}</Badge>;
}
