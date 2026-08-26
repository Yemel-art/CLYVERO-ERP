'use client';

import { toast } from 'sonner';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { useArchiveParent, useRestoreParent } from '@/hooks/parents';
import type { ParentGuardian } from '@/types/parent';

export function ArchiveParentDialog({ parent, open, onClose }: { parent: ParentGuardian; open: boolean; onClose: () => void }) {
  const archive = useArchiveParent();
  return (
    <Modal open={open} onClose={onClose} title="Archive parent?" size="sm"
      description={`${parent.full_name} will be archived and their portal login disabled.`}
      footer={<>
        <Button variant="outline" onClick={onClose} disabled={archive.isPending}>Cancel</Button>
        <Button variant="danger" isLoading={archive.isPending}
          onClick={async () => {
            try { await archive.mutateAsync(parent.id); toast.success(`${parent.full_name} archived.`); onClose(); }
            catch { toast.error('Could not archive parent.'); }
          }}>Archive parent</Button>
      </>}>
      <p className="text-sm text-secondary-600">Children remain linked but the parent cannot log in. You can restore this parent at any time.</p>
    </Modal>
  );
}

export function RestoreParentDialog({ parent, open, onClose }: { parent: ParentGuardian; open: boolean; onClose: () => void }) {
  const restore = useRestoreParent();
  return (
    <Modal open={open} onClose={onClose} title="Restore parent?" size="sm"
      description={`${parent.full_name} will be reactivated.`}
      footer={<>
        <Button variant="outline" onClick={onClose} disabled={restore.isPending}>Cancel</Button>
        <Button isLoading={restore.isPending}
          onClick={async () => {
            try { await restore.mutateAsync(parent.id); toast.success(`${parent.full_name} restored.`); onClose(); }
            catch { toast.error('Could not restore parent.'); }
          }}>Restore parent</Button>
      </>}>
      <p className="text-sm text-secondary-600">Their portal login will be reactivated.</p>
    </Modal>
  );
}
