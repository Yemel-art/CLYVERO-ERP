'use client';

import { useMemo, useState, useEffect } from 'react';
import Link from 'next/link';
import { Award, ArrowRight, Users } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAcademicYears, useTerms, useClasses } from '@/hooks/academic';
import { useTranslation } from '@/hooks/useTranslation';

export default function GradesHubPage() {
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((y) => y.status === 'active') ?? years?.[0], [years]);
  const [yearId, setYearId] = useState<string | undefined>(undefined);
  const effectiveYearId = yearId ?? activeYear?.id;

  const { data: terms } = useTerms(effectiveYearId);
  const activeTerm = useMemo(() => terms?.find((t) => t.status === 'active') ?? terms?.[0], [terms]);
  const [termId, setTermId] = useState<string | undefined>(undefined);
  const effectiveTermId = termId ?? activeTerm?.id;

  useEffect(() => { setTermId(undefined); }, [effectiveYearId]);

  const { data: classes, isLoading } = useClasses({ academic_year_id: effectiveYearId });

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: ui('Accueil', 'Home'), href: '/admin/dashboard' }, { label: ui('Notes', 'Grades') }]}
        title={ui('Notes et évaluations', 'Grades & Assessment')}
        description={ui('Choisissez une classe pour gérer les évaluations et saisir les notes du trimestre.', 'Pick a class to manage assessments and enter scores for the selected term.')}
      />

      <Card className="mb-6">
        <CardContent className="flex flex-wrap items-center gap-4 py-4">
          <Award className="h-5 w-5 text-secondary-500" />
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">{ui('Année scolaire', 'Academic year')}</label>
            <select value={effectiveYearId ?? ''} onChange={(e) => setYearId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              {(years ?? []).map((y) => <option key={y.id} value={y.id}>{y.title}</option>)}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">{ui('Trimestre', 'Term')}</label>
            <select value={effectiveTermId ?? ''} onChange={(e) => setTermId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              {(terms ?? []).map((t) => <option key={t.id} value={t.id}>{t.name} ({t.status})</option>)}
            </select>
          </div>
        </CardContent>
      </Card>

      {isLoading ? (
        <Skeleton className="h-48" />
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          {(classes ?? []).filter((c) => c.is_active).map((c) => (
            <Link
              key={c.id}
              href={`/admin/grades/${c.id}${effectiveTermId ? `?term=${effectiveTermId}` : ''}`}
              className="w-full rounded-card border border-secondary-200 bg-surface text-left shadow-card transition-shadow hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              <div className="px-6 py-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-semibold text-ink">{c.name}</p>
                    <p className="text-xs text-secondary-500">{c.grade_level}</p>
                    {c.form_master && <p className="mt-1 text-xs text-secondary-500">{c.form_master.full_name}</p>}
                  </div>
                  <div className="flex items-center gap-2">
                    <p className="flex items-center gap-1 text-sm text-secondary-500"><Users className="h-3 w-3" />{c.students_count ?? 0}</p>
                    <ArrowRight className="h-4 w-4 text-secondary-400" />
                  </div>
                </div>
              </div>
            </Link>
          ))}
          {(classes ?? []).length === 0 && (
            <Card className="md:col-span-2 lg:col-span-3"><CardContent className="py-12 text-center text-sm text-secondary-500">{ui('Aucune classe pour cette année.', 'No classes for this year yet.')}</CardContent></Card>
          )}
        </div>
      )}
    </div>
  );
}
