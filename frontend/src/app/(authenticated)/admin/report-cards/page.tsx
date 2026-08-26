'use client';

import { useState, useMemo, useEffect } from 'react';
import { toast } from 'sonner';
import { Download, Eye } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
import { useAcademicYears, useTerms, useClasses } from '@/hooks/academic';
import { studentsApi } from '@/lib/api/students';
import { apiClient } from '@/lib/api/client';
import { useTranslation } from '@/hooks/useTranslation';
import { useAuthStore } from '@/store/auth';

export default function ReportCardsPage() {
  const role = useAuthStore((s) => s.user?.role.name);
  const dashboardBase = role === 'secretary' ? '/secretary/dashboard' : '/admin/dashboard';
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const { data: years } = useAcademicYears();
  const activeYear = useMemo(() => years?.find((y) => y.status === 'active') ?? years?.[0], [years]);
  const [yearId, setYearId] = useState<string | undefined>(undefined);
  const effectiveYear = yearId ?? activeYear?.id;

  // Report-card term selector hardening.
  const { data: terms, isLoading: termsLoading, isError: termsError, refetch: refetchTerms } = useTerms(effectiveYear);
  const activeTerm = useMemo(() => terms?.find((t) => t.status === 'active') ?? terms?.[0], [terms]);
  const [termId, setTermId] = useState<string | undefined>(undefined);
  const effectiveTerm = termId ?? activeTerm?.id;
  const termLabel = (term: { name: string; sequence: number }) => ({ 1: ui('Premier trimestre', 'First Term'), 2: ui('Deuxième trimestre', 'Second Term'), 3: ui('Troisième trimestre', 'Third Term') }[term.sequence] ?? term.name);

  useEffect(() => { setTermId(undefined); }, [effectiveYear]);

  const { data: classes } = useClasses({ academic_year_id: effectiveYear });
  const [classId, setClassId] = useState<string | undefined>(undefined);

  const { data: students, isLoading } = useQuery({
    queryKey: ['students', 'by-class', classId],
    queryFn: () => studentsApi.list({ class_id: classId, per_page: 100 }),
    enabled: Boolean(classId),
  });

  const downloadFor = async (studentId: string, name: string) => {
    if (!effectiveTerm) return;
    try {
      const res = await apiClient.get(
        `/reports/students/${studentId}/terms/${effectiveTerm}/download`,
        { responseType: 'blob' },
      );
      const blob = new Blob([res.data], { type: 'application/pdf' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `report-card-${name.replace(/\s+/g, '-').toLowerCase()}.pdf`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
      toast.success(language === 'fr' ? `Bulletin de ${name} téléchargé.` : `Downloaded ${name}'s report card.`);
    } catch {
      toast.error(ui('Impossible de télécharger le bulletin.', 'Could not download report card.'));
    }
  };

  const previewFor = async (studentId: string) => {
    if (!effectiveTerm) return;
    try {
      const res = await apiClient.get(
        `/reports/students/${studentId}/terms/${effectiveTerm}/preview`,
        { responseType: 'blob' },
      );
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      window.open(url, '_blank', 'noopener,noreferrer');
      window.setTimeout(() => window.URL.revokeObjectURL(url), 60_000);
    } catch {
      toast.error(ui('Impossible d’afficher l’aperçu du bulletin.', 'Could not preview report card.'));
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: ui('Accueil', 'Home'), href: dashboardBase }, { label: ui('Bulletins', 'Report cards') }]}
        title={ui('Bulletins scolaires', 'Report cards')}
        description={ui('Générez les bulletins de fin de trimestre au format PDF.', 'Generate end-of-term report cards (PDF) for any student.')}
      />

      <Card className="mb-4">
        <CardContent className="flex flex-wrap items-end gap-3 py-4">
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">{ui('Année scolaire', 'Academic year')}</label>
            <select value={effectiveYear ?? ''} onChange={(e) => setYearId(e.target.value)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">{ui('Sélectionner', 'Select')}</option>
              {(years ?? []).map((y) => <option key={y.id} value={y.id}>{y.title}</option>)}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">{ui('Classe', 'Class')}</label>
            <select value={classId ?? ''} onChange={(e) => setClassId(e.target.value || undefined)}
              className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
              <option value="">{ui('Sélectionner une classe', 'Select class')}</option>
              {(classes ?? []).filter((c) => c.is_active).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-xs font-medium text-secondary-700">{ui('Trimestre', 'Term')}</label>
            <select value={effectiveTerm ?? ''} onChange={(e) => setTermId(e.target.value || undefined)} disabled={!effectiveYear || termsLoading || termsError}
              className="h-10 min-w-44 rounded-input border border-secondary-300 bg-surface px-3 text-sm disabled:cursor-not-allowed disabled:bg-secondary-100">
              <option value="">{termsLoading ? ui('Chargement…', 'Loading…') : termsError ? ui('Erreur de chargement', 'Loading error') : (terms ?? []).length === 0 ? ui('Aucun trimestre configuré', 'No terms configured') : ui('Sélectionner', 'Select')}</option>
              {(terms ?? []).map((t) => <option key={t.id} value={t.id}>{termLabel(t)}{t.status === 'active' ? ui(' — actif', ' — active') : ''}</option>)}
            </select>
          </div>

        </CardContent>
      </Card>

      {termsError && <Card className="mb-4 border-danger/30"><CardContent className="flex flex-wrap items-center justify-between gap-3 py-4 text-sm text-danger"><span>{ui('Impossible de charger les trimestres. Vérifiez votre connexion puis réessayez.', 'Could not load terms. Check your connection and try again.')}</span><Button size="sm" variant="outline" onClick={() => void refetchTerms()}>{ui('Réessayer', 'Retry')}</Button></CardContent></Card>}
      {!termsLoading && !termsError && effectiveYear && (terms ?? []).length === 0 && <Card className="mb-4 border-warning/30"><CardContent className="py-4 text-sm text-secondary-700">{ui('Aucun trimestre n’est configuré pour cette année scolaire. Demandez à l’administrateur d’ouvrir Structure académique → Trimestres.', 'No term is configured for this academic year. Ask the administrator to open Academic structure → Terms.')}</CardContent></Card>}

      {!classId || !effectiveTerm ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">
          {ui('Choisissez une classe et un trimestre pour afficher les élèves.', 'Pick a class and a term to list students.')}
        </CardContent></Card>
      ) : isLoading ? <Skeleton className="h-48" /> : (students?.data ?? []).length === 0 ? (
        <Card><CardContent className="py-12 text-center text-sm text-secondary-500">{ui('Aucun élève dans cette classe.', 'No students in this class.')}</CardContent></Card>
      ) : (
        <Card>
          <CardContent className="py-2">
            <ul className="divide-y divide-secondary-100">
              {(students?.data ?? []).map((s) => (
                <li key={s.id} className="flex items-center justify-between py-3">
                  <div className="flex items-center gap-3">
                    {s.photo_url
                      ? <img src={s.photo_url} alt="" className="h-9 w-9 rounded-full object-cover" />
                      : <Avatar name={s.full_name} size="sm" />}
                    <div>
                      <p className="font-medium text-ink">{s.full_name}</p>
                      <p className="text-xs text-secondary-500">{s.admission_number}</p>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button size="sm" variant="outline" leftIcon={<Eye className="h-4 w-4" />}
                      onClick={() => previewFor(s.id)}>{ui('Aperçu', 'Preview')}</Button>
                    <Button size="sm" leftIcon={<Download className="h-4 w-4" />}
                      onClick={() => downloadFor(s.id, s.full_name)}>{ui('Télécharger', 'Download')}</Button>
                  </div>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
