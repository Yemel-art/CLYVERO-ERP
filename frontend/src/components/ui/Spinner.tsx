import { cn } from '@/lib/utils/cn';

interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
  label?: string;
}

export function Spinner({ size = 'md', className, label = 'Loading' }: SpinnerProps) {
  const dims = { sm: 'h-4 w-4', md: 'h-6 w-6', lg: 'h-10 w-10' }[size];
  return (
    <div
      role="status"
      aria-label={label}
      className={cn('inline-block animate-spin rounded-full border-2 border-secondary-200 border-t-primary-600', dims, className)}
    >
      <span className="sr-only">{label}</span>
    </div>
  );
}
