'use client';

import { useState, useEffect } from 'react';
import { Drawer } from '@/components/ui/Drawer';
import { Button } from '@/components/ui/Button';
import { GENDER_OPTIONS, STATUS_OPTIONS, type StudentFilters } from '@/types/student';
import { cn } from '@/lib/utils/cn';

interface Props {
  open: boolean;
  onClose: () => void;
  initial: StudentFilters;
  onApply: (filters: Partial<StudentFilters>) => void;
}

export function StudentFiltersDrawer({ open, onClose, initial, onApply }: Props) {
  const [local, setLocal] = useState<StudentFilters>(initial);

  useEffect(() => {
    if (open) setLocal(initial);
  }, [open, initial]);

  const apply = () => {
    onApply({
      status: local.status,
      gender: local.gender,
      include_archived: local.include_archived,
    });
    onClose();
  };

  const reset = () => {
    setLocal({});
    onApply({ status: undefined, gender: undefined, include_archived: false });
    onClose();
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="Filter students"
      footer={
        <>
          <Button variant="ghost" onClick={reset}>Reset</Button>
          <Button onClick={apply}>Apply filters</Button>
        </>
      }
    >
      <div className="space-y-6">
        <fieldset>
          <legend className="mb-2 text-sm font-medium text-secondary-700">Status</legend>
          <div className="flex flex-wrap gap-2">
            {STATUS_OPTIONS.map((opt) => {
              const selected = local.status === opt.value;
              return (
                <button
                  key={opt.value}
                  type="button"
                  onClick={() => setLocal((s) => ({ ...s, status: selected ? undefined : opt.value }))}
                  className={cn(
                    'rounded-button border px-3 py-1.5 text-sm transition-colors',
                    selected
                      ? 'border-primary-600 bg-primary-50 text-primary-700'
                      : 'border-secondary-300 bg-surface text-secondary-700 hover:bg-secondary-50',
                  )}
                >
                  {opt.label}
                </button>
              );
            })}
          </div>
        </fieldset>

        <fieldset>
          <legend className="mb-2 text-sm font-medium text-secondary-700">Gender</legend>
          <div className="flex flex-wrap gap-2">
            {GENDER_OPTIONS.map((opt) => {
              const selected = local.gender === opt.value;
              return (
                <button
                  key={opt.value}
                  type="button"
                  onClick={() => setLocal((s) => ({ ...s, gender: selected ? undefined : opt.value }))}
                  className={cn(
                    'rounded-button border px-3 py-1.5 text-sm transition-colors',
                    selected
                      ? 'border-primary-600 bg-primary-50 text-primary-700'
                      : 'border-secondary-300 bg-surface text-secondary-700 hover:bg-secondary-50',
                  )}
                >
                  {opt.label}
                </button>
              );
            })}
          </div>
        </fieldset>

        <label className="flex items-center gap-2 text-sm text-secondary-700">
          <input
            type="checkbox"
            checked={Boolean(local.include_archived)}
            onChange={(e) => setLocal((s) => ({ ...s, include_archived: e.target.checked }))}
            className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
          />
          Include archived students
        </label>
      </div>
    </Drawer>
  );
}
