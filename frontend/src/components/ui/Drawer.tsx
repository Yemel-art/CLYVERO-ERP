'use client';

import { useEffect, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils/cn';

interface DrawerProps {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  footer?: ReactNode;
  width?: 'sm' | 'md' | 'lg';
}

const widthStyles = {
  sm: 'w-80',
  md: 'w-96',
  lg: 'w-[28rem]',
};

/**
 * Right-side drawer used for filters and quick details (per Design
 * Philosophy: filters always open in a drawer from the right).
 */
export function Drawer({ open, onClose, title, children, footer, width = 'md' }: DrawerProps) {
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (typeof window === 'undefined') return null;

  return createPortal(
    <div
      className={cn(
        'fixed inset-0 z-40',
        open ? 'pointer-events-auto' : 'pointer-events-none',
      )}
      aria-hidden={!open}
    >
      <div
        className={cn(
          'absolute inset-0 bg-black/40 transition-opacity',
          open ? 'opacity-100' : 'opacity-0',
        )}
        onClick={onClose}
      />
      <aside
        role="dialog"
        aria-modal="true"
        aria-label={title}
        className={cn(
          'absolute right-0 top-0 flex h-full flex-col bg-surface shadow-modal transition-transform duration-200',
          widthStyles[width],
          open ? 'translate-x-0' : 'translate-x-full',
        )}
      >
        <header className="flex items-center justify-between border-b border-secondary-200 px-6 py-4">
          <h2 className="text-lg font-semibold text-ink">{title}</h2>
          <button
            onClick={onClose}
            aria-label="Close panel"
            className="rounded-button p-1 text-secondary-400 hover:bg-secondary-100 hover:text-secondary-700"
          >
            <X className="h-5 w-5" />
          </button>
        </header>
        <div className="flex-1 overflow-y-auto px-6 py-4">{children}</div>
        {footer && (
          <footer className="flex items-center justify-end gap-3 border-t border-secondary-200 px-6 py-4">
            {footer}
          </footer>
        )}
      </aside>
    </div>,
    document.body,
  );
}
