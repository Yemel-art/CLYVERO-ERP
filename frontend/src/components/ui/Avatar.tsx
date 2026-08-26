import { cn } from '@/lib/utils/cn';

interface AvatarProps {
  name: string;
  src?: string | null;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join('');
}

export function Avatar({ name, src, size = 'md', className }: AvatarProps) {
  const dims = { sm: 'h-8 w-8 text-xs', md: 'h-10 w-10 text-sm', lg: 'h-12 w-12 text-base' }[size];

  if (src) {
    // eslint-disable-next-line @next/next/no-img-element
    return (
      <img
        src={src}
        alt={name}
        className={cn('rounded-full object-cover', dims, className)}
      />
    );
  }

  return (
    <div
      aria-label={name}
      className={cn(
        'inline-flex items-center justify-center rounded-full bg-primary-100 font-semibold text-primary-700',
        dims,
        className,
      )}
    >
      {initials(name)}
    </div>
  );
}
