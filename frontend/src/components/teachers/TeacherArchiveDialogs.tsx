'use client';

import { toast } from 'sonner';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { useArchiveTeacher, useRestoreTeacher } from '@/hooks/teachers';
import type { Teacher } from '@/types/teacher';

export function ArchiveTeacherDialog({ teacher, open, onClose }: { teacher: Teacher; open: boolean; onClose: () => void }) {
  const archive = useArchiveTeacher();
  return (
    <Modal open={open} onClose={onClose} title="Archive teacher?" size="sm"
      description={`${teacher.full_name} (${teacher.employee_number}) will be moved to the archive and their login deactivated.`}
      footer={<>
        <Button variant="outline" onClick={onClose} disabled={archive.isPending}>Cancel</Button>
        <Button variant="danger" isLoading={archive.isPending}
          onClick={async () => {
            try { await archive.mutateAsync(teacher.id); toast.success(`${teacher.full_name} archived.`); onClose(); }
            catch { toast.error('Could not archive teacher.'); }
          }}>Archive teacher</Button>
      </>}>
      <p className="text-sm text-secondary-600">Archived teachers no longer appear in the active roster but their records and any historical classes are preserved.</p>
    </Modal>
  );
}

export function RestoreTeacherDialog({ teacher, open, onClose }: { teacher: Teacher; open: boolean; onClose: () => void }) {
  const restore = useRestoreTeacher();
  return (
    <Modal open={open} onClose={onClose} title="Restore teacher?" size="sm"
      description={`${teacher.full_name} will return to the active roster and their login will be reactivated.`}
      footer={<>
        <Button variant="outline" onClick={onClose} disabled={restore.isPending}>Cancel</Button>
        <Button isLoading={restore.isPending}
          onClick={async () => {
            try { await restore.mutateAsync(teacher.id); toast.success(`${teacher.full_name} restored.`); onClose(); }
            catch { toast.error('Could not restore teacher.'); }
          }}>Restore teacher</Button>
      </>}>
      <p className="text-sm text-secondary-600">The teacher will be marked active again.</p>
    </Modal>
  );
}
