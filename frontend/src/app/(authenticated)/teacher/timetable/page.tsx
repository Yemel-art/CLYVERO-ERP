'use client';

import { Printer } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useMyTimetable } from '@/hooks/timetable';
import { DAYS } from '@/types/timetable';

export default function TeacherTimetablePage() {
  const { data: slots, isLoading } = useMyTimetable();

  if (isLoading) return <Skeleton className="h-96" />;

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: '/teacher/dashboard' }, { label: 'My timetable' }]}
        title="My timetable"
        description="Your weekly teaching schedule."
        actions={
          <Button variant="outline" leftIcon={<Printer className="h-4 w-4" />} onClick={() => window.print()}>
            Print / PDF
          </Button>
        }
      />
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {DAYS.map((day) => {
          const daySlots = (slots ?? []).filter((slot) => slot.day_of_week === day.value);
          return (
            <Card key={day.value}>
              <CardContent className="py-4">
                <h2 className="mb-3 font-semibold text-ink">{day.label}</h2>
                <div className="space-y-2">
                  {daySlots.map((slot) => (
                    <div key={slot.id} className="rounded-card border border-secondary-200 p-3 text-sm">
                      <p className="font-semibold">{slot.subject?.name}</p>
                      <p>{slot.start_time}–{slot.end_time}</p>
                      <p className="text-secondary-500">{slot.class?.name}{slot.room ? ` · Room ${slot.room}` : ''}</p>
                    </div>
                  ))}
                  {daySlots.length === 0 && <p className="text-sm text-secondary-400">No lessons</p>}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
