import type { ReactNode } from 'react';
import { Breadcrumb } from './Breadcrumb';

interface PageHeaderProps {
  breadcrumb?: { label: string; href?: string }[];
  title: string;
  description?: string;
  actions?: ReactNode;
}

/**
 * Standard page header per Design Philosophy structure:
 * breadcrumb → title → primary action.
 */
export function PageHeader({ breadcrumb, title, description, actions }: PageHeaderProps) {
  return (
    <div className="mb-6 flex flex-col gap-3">
      {breadcrumb && breadcrumb.length > 0 && <Breadcrumb items={breadcrumb} />}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-ink">{title}</h1>
          {description && <p className="mt-1 text-sm text-secondary-500">{description}</p>}
        </div>
        {actions && <div className="flex items-center gap-2">{actions}</div>}
      </div>
    </div>
  );
}
