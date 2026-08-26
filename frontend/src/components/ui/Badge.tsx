import { cn } from '@/lib/utils/cn';
import type { HTMLAttributes } from 'react';

type Variant = 'default' | 'success' | 'warning' | 'danger' | 'info' | 'secondary';

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: Variant;
}

const styles: Record<Variant, string> = {
  default:   'bg-primary-100 text-primary-700',
  success:   'bg-success-light text-success-dark',
  warning:   'bg-warning-light text-warning-dark',
  danger:    'bg-danger-light text-danger-dark',
  info:      'bg-info-light text-info-dark',
  secondary: 'bg-secondary-100 text-secondary-700',
};

export function Badge({ variant = 'default', className, ...props }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        styles[variant],
        className,
      )}
      {...props}
    />
  );
}
