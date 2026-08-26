'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { useArchiveStudent, usePermanentlyDeleteStudent, useRestoreStudent } from '@/hooks/students';
import { Input } from '@/components/ui/Input';
import type { Student } from '@/types/student';

interface ArchiveProps {
  student: Student;
  open: boolean;
  onClose: () => void;
  onDone?: (student: Student) => void;
}

export function ArchiveStudentDialog({ student, open, onClose, onDone }: ArchiveProps) {
  const archive = useArchiveStudent();

  const handle = async () => {
    try {
      const updated = await archive.mutateAsync(student.id);
      toast.success(`${student.full_name} has been archived.`);
      onClose();
      onDone?.(updated);
    } catch {
      toast.error('Could not archive the student. Please try again.');
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Archive student?"
      description={`${student.full_name} (${student.admission_number}) will be moved to the archive.`}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={archive.isPending}>Cancel</Button>
          <Button variant="danger" onClick={handle} isLoading={archive.isPending}>Archive student</Button>
        </>
      }
    >
      <p className="text-sm text-secondary-600">
        Archived students are hidden from the active roster but their academic and financial
        records are preserved. You can restore them at any time from the archived list.
      </p>
    </Modal>
  );
}

interface RestoreProps {
  student: Student;
  open: boolean;
  onClose: () => void;
  onDone?: (student: Student) => void;
}

export function RestoreStudentDialog({ student, open, onClose, onDone }: RestoreProps) {
  const restore = useRestoreStudent();

  const handle = async () => {
    try {
      const updated = await restore.mutateAsync(student.id);
      toast.success(`${student.full_name} has been restored.`);
      onClose();
      onDone?.(updated);
    } catch {
      toast.error('Could not restore the student.');
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Restore student?"
      description={`${student.full_name} will return to the active roster.`}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={restore.isPending}>Cancel</Button>
          <Button onClick={handle} isLoading={restore.isPending}>Restore student</Button>
        </>
      }
    >
      <p className="text-sm text-secondary-600">
        The student will be reactivated with their previous admission number and records intact.
      </p>
    </Modal>
  );
}

export function PermanentlyDeleteStudentDialog({ student, open, onClose, onDone }: ArchiveProps) {
  const remove = usePermanentlyDeleteStudent();
  const [confirmation, setConfirmation] = useState('');

  const handle = async () => {
    try {
      await remove.mutateAsync({ id: student.id, confirmation });
      toast.success(`${student.full_name} was permanently deleted.`);
      onDone?.(student);
      setConfirmation('');
      onClose();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Could not permanently delete the student.');
    }
  };

  return <Modal open={open} onClose={onClose} title="Permanently delete student?" size="sm"
    description="This cannot be undone. Student records, invoices, payments, grades, attendance, enrollments, and stored photo will be removed."
    footer={<><Button variant="outline" onClick={onClose} disabled={remove.isPending}>Cancel</Button><Button variant="danger" onClick={handle} disabled={confirmation !== student.admission_number} isLoading={remove.isPending}>Permanently delete</Button></>}>
    <div className="space-y-3"><p className="text-sm text-secondary-600">Type <strong>{student.admission_number}</strong> exactly to confirm.</p><Input label="Admission number confirmation" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} autoComplete="off" /></div>
  </Modal>;
}
