import { forwardRef, useId, type InputHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@/lib/utils/cn';

interface InputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> {
  label?: string;
  error?: string;
  hint?: string;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  containerClassName?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, hint, leftIcon, rightIcon, className, containerClassName, id, ...rest }, ref) => {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const describedBy = error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined;

    return (
      <div className={cn('flex flex-col gap-1.5', containerClassName)}>
        {label && (
          <label htmlFor={inputId} className="text-sm font-medium text-secondary-700">
            {label}
            {rest.required && <span className="ml-0.5 text-danger">*</span>}
          </label>
        )}
        <div className="relative">
          {leftIcon && (
            <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-secondary-400">
              {leftIcon}
            </span>
          )}
          <input
            ref={ref}
            id={inputId}
            aria-invalid={Boolean(error)}
            aria-describedby={describedBy}
            className={cn(
              'h-10 w-full rounded-input border bg-surface px-3 text-sm text-ink',
              'placeholder:text-secondary-400',
              'focus:border-primary-500',
              'disabled:cursor-not-allowed disabled:bg-secondary-50 disabled:text-secondary-400',
              leftIcon && 'pl-10',
              rightIcon && 'pr-10',
              error
                ? 'border-danger focus:ring-danger/30'
                : 'border-secondary-300',
              className,
            )}
            {...rest}
          />
          {rightIcon && (
            <span className="absolute inset-y-0 right-3 flex items-center text-secondary-400">
              {rightIcon}
            </span>
          )}
        </div>
        {error ? (
          <p id={`${inputId}-error`} className="text-sm text-danger">
            {error}
          </p>
        ) : hint ? (
          <p id={`${inputId}-hint`} className="text-sm text-secondary-500">
            {hint}
          </p>
        ) : null}
      </div>
    );
  },
);

Input.displayName = 'Input';
