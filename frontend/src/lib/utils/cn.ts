import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge Tailwind class names, de-duplicating conflicting utilities.
 * The canonical pattern: `cn('px-2 py-1', isActive && 'bg-primary-600')`.
 */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}
