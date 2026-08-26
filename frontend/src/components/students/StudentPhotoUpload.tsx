'use client';

import { useRef, useState } from 'react';
import { Camera, Upload, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Avatar } from '@/components/ui/Avatar';
import { Button } from '@/components/ui/Button';
import { useUploadStudentPhoto, useRemoveStudentPhoto } from '@/hooks/students';
import type { Student } from '@/types/student';

interface Props {
  student: Student;
}

const ALLOWED = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const MAX_BYTES = 5 * 1024 * 1024;

export function StudentPhotoUpload({ student }: Props) {
  const upload = useUploadStudentPhoto(student.id);
  const remove = useRemoveStudentPhoto(student.id);
  const fileRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);

  const onFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!ALLOWED.includes(file.type)) {
      toast.error('Please choose a JPEG, PNG, or WebP image.');
      return;
    }
    if (file.size > MAX_BYTES) {
      toast.error('The photo must be smaller than 5 MB.');
      return;
    }

    setBusy(true);
    try {
      await upload.mutateAsync(file);
      toast.success('Photo updated.');
    } catch {
      toast.error('Could not upload the photo. Please try again.');
    } finally {
      setBusy(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  const onRemove = async () => {
    setBusy(true);
    try {
      await remove.mutateAsync();
      toast.success('Photo removed.');
    } catch {
      toast.error('Could not remove the photo.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex items-center gap-4">
      <div className="relative">
        {student.photo_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={student.photo_url}
            alt={student.full_name}
            className="h-24 w-24 rounded-full object-cover ring-2 ring-secondary-100"
          />
        ) : (
          <Avatar name={student.full_name} size="lg" className="h-24 w-24 text-2xl" />
        )}
        <button
          type="button"
          onClick={() => fileRef.current?.click()}
          disabled={busy}
          aria-label="Change photo"
          className="absolute -bottom-1 -right-1 rounded-full bg-primary-600 p-2 text-white shadow-card hover:bg-primary-700 disabled:opacity-60"
        >
          <Camera className="h-4 w-4" />
        </button>
      </div>

      <div className="flex flex-col gap-2">
        <Button
          variant="outline"
          size="sm"
          leftIcon={<Upload className="h-4 w-4" />}
          onClick={() => fileRef.current?.click()}
          isLoading={busy && upload.isPending}
        >
          {student.photo_url ? 'Change photo' : 'Upload photo'}
        </Button>
        {student.photo_url && (
          <Button
            variant="ghost"
            size="sm"
            leftIcon={<Trash2 className="h-4 w-4" />}
            onClick={onRemove}
            isLoading={busy && remove.isPending}
          >
            Remove
          </Button>
        )}
      </div>

      <input ref={fileRef} type="file" hidden accept="image/jpeg,image/jpg,image/png,image/webp" onChange={onFile} />
    </div>
  );
}
