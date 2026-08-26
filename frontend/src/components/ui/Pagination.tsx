'use client';

import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils/cn';

interface PaginationProps {
  page: number;
  lastPage: number;
  total: number;
  perPage: number;
  onPageChange: (page: number) => void;
}

export function Pagination({ page, lastPage, total, perPage, onPageChange }: PaginationProps) {
  if (lastPage <= 1) return null;

  const start = (page - 1) * perPage + 1;
  const end = Math.min(page * perPage, total);

  // Build a windowed page list (1 … current-1 current current+1 … last)
  const pages: (number | 'ellipsis')[] = [];
  const window = 1;
  for (let i = 1; i <= lastPage; i++) {
    if (i === 1 || i === lastPage || (i >= page - window && i <= page + window)) {
      pages.push(i);
    } else if (pages[pages.length - 1] !== 'ellipsis') {
      pages.push('ellipsis');
    }
  }

  return (
    <nav className="flex items-center justify-between gap-4 py-3" aria-label="Pagination">
      <p className="text-sm text-secondary-500">
        Showing <span className="font-medium text-ink">{start}</span> to{' '}
        <span className="font-medium text-ink">{end}</span> of{' '}
        <span className="font-medium text-ink">{total}</span> results
      </p>
      <div className="flex items-center gap-1">
        <button
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
          aria-label="Previous page"
          className="rounded-button p-2 text-secondary-500 hover:bg-secondary-100 hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
        >
          <ChevronLeft className="h-4 w-4" />
        </button>
        {pages.map((p, i) =>
          p === 'ellipsis' ? (
            <span key={`e-${i}`} className="px-2 text-secondary-400">…</span>
          ) : (
            <button
              key={p}
              onClick={() => onPageChange(p)}
              aria-current={p === page ? 'page' : undefined}
              className={cn(
                'min-w-[2rem] rounded-button px-2.5 py-1.5 text-sm font-medium',
                p === page
                  ? 'bg-primary-600 text-white'
                  : 'text-secondary-700 hover:bg-secondary-100',
              )}
            >
              {p}
            </button>
          ),
        )}
        <button
          onClick={() => onPageChange(page + 1)}
          disabled={page >= lastPage}
          aria-label="Next page"
          className="rounded-button p-2 text-secondary-500 hover:bg-secondary-100 hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
        >
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>
    </nav>
  );
}
