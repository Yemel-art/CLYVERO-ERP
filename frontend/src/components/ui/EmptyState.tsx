import type { ReactNode } from 'react';
import { cn } from '@/lib/utils/cn';

interface EmptyStateProps {
  icon?: ReactNode;
  title: string;
  description?: string;
  action?: ReactNode;
  className?: string;
}

export function EmptyState({ icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center rounded-card border border-dashed border-secondary-300 bg-surface px-6 py-12 text-center',
        className,
      )}
    >
      {icon && <div className="mb-4 text-secondary-400">{icon}</div>}
      <h3 className="text-base font-semibold text-ink">{title}</h3>
      {description && <p className="mt-1 max-w-md text-sm text-secondary-500">{description}</p>}
      {action && <div className="mt-6">{action}</div>}
    </div>
  );
}
