'use client';

import { useRef } from 'react';
import { Camera, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { Avatar } from '@/components/ui/Avatar';
import { Button } from '@/components/ui/Button';
import { useRemoveTeacherPhoto, useUploadTeacherPhoto } from '@/hooks/teachers';
import type { Teacher } from '@/types/teacher';

const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_BYTES = 5 * 1024 * 1024;

export function TeacherPhotoUpload({ teacher }: { teacher: Teacher }) {
  const input = useRef<HTMLInputElement>(null);
  const upload = useUploadTeacherPhoto(teacher.id);
  const remove = useRemoveTeacherPhoto(teacher.id);
  const busy = upload.isPending || remove.isPending;

  const selectPhoto = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!ALLOWED.includes(file.type)) {
      toast.error('Choose a JPEG, PNG, or WebP image.');
    } else if (file.size > MAX_BYTES) {
      toast.error('The photo must be smaller than 5 MB.');
    } else {
      try {
        await upload.mutateAsync(file);
        toast.success('Teacher photo updated.');
      } catch (error: any) {
        toast.error(error?.response?.data?.message ?? 'Could not upload the photo.');
      }
    }
    event.target.value = '';
  };

  return (
    <div className="flex shrink-0 flex-col items-center gap-2">
      <button type="button" onClick={() => input.current?.click()} disabled={busy}
        className="relative rounded-full" aria-label="Change teacher photo">
        {teacher.photo_url
          ? <img src={teacher.photo_url} alt={teacher.full_name} className="h-24 w-24 rounded-full object-cover ring-2 ring-secondary-100" />
          : <Avatar name={teacher.full_name} size="lg" className="h-24 w-24 text-2xl" />}
        <span className="absolute -bottom-1 -right-1 rounded-full bg-primary-600 p-2 text-white shadow-card">
          <Camera className="h-4 w-4" />
        </span>
      </button>
      {teacher.photo_url && (
        <Button size="sm" variant="ghost" leftIcon={<Trash2 className="h-3 w-3" />}
          onClick={() => remove.mutateAsync().catch(() => toast.error('Could not remove the photo.'))}
          isLoading={remove.isPending}>Remove</Button>
      )}
      <input ref={input} hidden type="file" accept="image/jpeg,image/png,image/webp" onChange={selectPhoto} />
    </div>
  );
}
