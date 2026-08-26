'use client';

import { useRouter } from 'next/navigation';
import { Calendar, Clock, BookOpen, Users, TrendingUp } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';
import { useAcademicYears, useSubjects, useClasses } from '@/hooks/academic';

const SECTIONS = [
  { id: 'years',    label: 'Academic years', icon: Calendar,  path: '/admin/academic/years',    description: 'Set up the school calendar and activate the running year.' },
  { id: 'terms',    label: 'Terms',          icon: Clock,     path: '/admin/academic/terms',    description: 'Manage the three terms within each academic year.' },
  { id: 'subjects', label: 'Subjects',       icon: BookOpen,  path: '/admin/academic/subjects', description: 'Define the courses that can be taught.' },
  { id: 'classes',  label: 'Classes',        icon: Users,     path: '/admin/academic/classes',  description: 'Form sections, assign form masters, and attach subjects.' },
  { id: 'progression', label: 'Academic progression', icon: TrendingUp, path: '/admin/academic/progression', description: 'Configure promotion rules and review final academic decisions.' },
];

export default function AcademicHubPage() {
  const router = useRouter();
  const { data: years } = useAcademicYears();
  const { data: subjects } = useSubjects();
  const { data: classes } = useClasses();
  const activeYear = years?.find((y) => y.status === 'active');

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: 'Home', href: '/admin/dashboard' }, { label: 'Academic structure' }]}
        title="Academic structure"
        description={activeYear ? `Currently active year: ${activeYear.title}` : 'No active academic year yet — create one to get started.'}
      />

      <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">Years</p><p className="text-2xl font-semibold text-ink">{years?.length ?? '—'}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">Subjects</p><p className="text-2xl font-semibold text-ink">{subjects?.length ?? '—'}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">Classes</p><p className="text-2xl font-semibold text-ink">{classes?.length ?? '—'}</p></CardContent></Card>
        <Card><CardContent className="py-4"><p className="text-sm text-secondary-500">Active year</p><p className="text-base font-semibold text-ink truncate">{activeYear?.title ?? 'None'}</p></CardContent></Card>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {SECTIONS.map((s) => {
          const Icon = s.icon;
          return (
            <button key={s.id} onClick={() => router.push(s.path)}
              className="rounded-card border border-secondary-200 bg-surface p-6 text-left transition-shadow hover:shadow-card">
              <div className="flex items-center gap-3">
                <div className="rounded-button bg-primary-50 p-2 text-primary-600"><Icon className="h-5 w-5" /></div>
                <h3 className="text-lg font-semibold text-ink">{s.label}</h3>
              </div>
              <p className="mt-2 text-sm text-secondary-500">{s.description}</p>
            </button>
          );
        })}
      </div>
    </div>
  );
}
