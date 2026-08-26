'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { Building2, GraduationCap, ShieldCheck, UserCog, Users, Plus, ArrowRight, RefreshCw, AlertCircle } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { platformApi } from '@/lib/api/platform';
import { useTranslation } from '@/hooks/useTranslation';

export default function PlatformDashboardPage() {
  const router = useRouter();
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const { data, isLoading, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['platform', 'overview'],
    queryFn: ({ signal }) => platformApi.overview(signal),
  });
  const metrics = [
    { label: ui('Écoles actives', 'Active schools'), value: data?.schools_active ?? 0, icon: Building2 },
    { label: ui('Comptes proviseurs', 'Principal accounts'), value: data?.school_administrators ?? 0, icon: ShieldCheck },
    { label: ui('Enseignants', 'Teachers'), value: data?.teachers_total ?? 0, icon: UserCog },
    { label: ui('Élèves', 'Students'), value: data?.students_total ?? 0, icon: GraduationCap },
  ];

  return <div>
    <PageHeader
      title={ui('Console propriétaire CLYVERO', 'CLYVERO owner console')}
      description={ui('Espace privé pour créer, activer et personnaliser les établissements clients.', 'Private workspace for creating, activating and customizing customer schools.')}
      actions={<Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => router.push('/platform/schools')}>{ui('Créer une école', 'Create school')}</Button>}
    />
    {isLoading ? <Skeleton className="h-40" /> : isError ? (
      <Card className="border-danger/30">
        <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
          <AlertCircle className="h-8 w-8 text-danger" />
          <div>
            <p className="font-semibold text-ink">{ui('Impossible de charger la console', 'Unable to load the console')}</p>
            <p className="mt-1 text-sm text-secondary-600">
              {error instanceof Error ? error.message : ui('Une erreur réseau est survenue.', 'A network error occurred.')}
            </p>
          </div>
          <Button variant="outline" leftIcon={<RefreshCw className="h-4 w-4" />} isLoading={isFetching} onClick={() => void refetch()}>
            {ui('Réessayer', 'Try again')}
          </Button>
        </CardContent>
      </Card>
    ) : <>
      <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{metrics.map((metric) => <Card key={metric.label}><CardContent className="flex items-center gap-4 py-5"><div className="rounded-full bg-primary-50 p-3 text-primary-700"><metric.icon className="h-5 w-5" /></div><div><p className="text-sm text-secondary-500">{metric.label}</p><p className="text-2xl font-semibold text-ink">{metric.value}</p></div></CardContent></Card>)}</div>
      <div className="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <Card><CardHeader><CardTitle>{ui('Écoles récemment créées', 'Recently created schools')}</CardTitle><CardDescription>{ui(`${data?.schools_total ?? 0} école(s) au total, dont ${data?.schools_inactive ?? 0} inactive(s).`, `${data?.schools_total ?? 0} total school(s), ${data?.schools_inactive ?? 0} inactive.`)}</CardDescription></CardHeader><CardContent className="space-y-3">{(data?.recent_schools ?? []).map((school) => <Link key={school.id} href="/platform/schools" className="flex items-center justify-between rounded-card border border-secondary-200 p-3 hover:border-primary-300 hover:bg-primary-50"><div className="flex min-w-0 items-center gap-3">{school.logo_url ? <img src={school.logo_url} alt="" className="h-10 w-10 shrink-0 rounded object-contain" /> : <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-primary-50"><Building2 className="h-5 w-5 text-primary-600" /></div>}<div className="min-w-0"><p className="truncate font-medium text-ink">{school.school_name}</p><p className="text-xs text-secondary-500">{school.school_code} · {school.current_academic_year?.title ?? ui('Aucune année active', 'No active year')}</p></div></div><ArrowRight className="h-4 w-4 shrink-0 text-primary-600" /></Link>)}{(data?.recent_schools ?? []).length === 0 && <p className="py-8 text-center text-sm text-secondary-500">{ui('Aucune école créée.', 'No school created yet.')}</p>}</CardContent></Card>
        <Card><CardHeader><CardTitle>{ui('Parcours sécurisé', 'Secure workflow')}</CardTitle></CardHeader><CardContent><ol className="space-y-3 text-sm text-secondary-700"><li>1. {ui('Créer l’école et son code unique.', 'Create the school and unique code.')}</li><li>2. {ui('Définir l’e-mail et le mot de passe temporaire du proviseur.', 'Set the principal email and temporary password.')}</li><li>3. {ui('Ouvrir « Branding & settings » pour charger les logos et en-têtes.', 'Open Branding & settings to upload logos and headers.')}</li><li>4. {ui('Transmettre uniquement au proviseur le code école et ses identifiants.', 'Give only that principal the school code and credentials.')}</li></ol><Button variant="outline" className="mt-5 w-full" leftIcon={<Users className="h-4 w-4" />} onClick={() => router.push('/platform/schools')}>{ui('Gérer les écoles', 'Manage schools')}</Button></CardContent></Card>
      </div>
    </>}
  </div>;
}
