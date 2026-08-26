'use client';

import { Wallet } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Card, CardContent } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { useParentDashboard } from '@/hooks/dashboard';
import { useTranslation } from '@/hooks/useTranslation';

function formatXAF(n: number) {
  return new Intl.NumberFormat('fr-CM').format(Math.round(n)) + ' XAF';
}

export default function ParentDashboardPage() {
  const { data, isLoading } = useParentDashboard();
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  if (isLoading || !data) {
    return <><PageHeader title={ui('Ma famille', 'My family')} description={ui('Chargement…', 'Loading…')} /><Skeleton className="h-64" /></>;
  }

  return (
    <div>
      <PageHeader title={`${ui('Bienvenue', 'Welcome')}, ${data.parent?.full_name ?? ''}`}
        description={language === 'fr'
          ? `${data.children.length} enfant${data.children.length > 1 ? 's' : ''} lié${data.children.length > 1 ? 's' : ''} à votre portail.`
          : `${data.children.length} child${data.children.length === 1 ? '' : 'ren'} linked to your portal.`} />

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
        {data.children.length === 0 ? (
          <Card className="md:col-span-2 lg:col-span-3"><CardContent className="py-12 text-center text-sm text-secondary-500">
            {ui('Aucun enfant n’est encore lié à votre compte. Veuillez contacter le secrétariat.', 'No children linked to your account yet. Please contact the school office.')}
          </CardContent></Card>
        ) : data.children.map((c) => (
          <Card key={c.id}>
            <CardContent className="py-5">
              <div className="flex items-center gap-3">
                {c.photo_url
                  ? <img src={c.photo_url} alt="" className="h-14 w-14 rounded-full object-cover" />
                  : <Avatar name={c.full_name} size="lg" />}
                <div className="flex-1">
                  <p className="font-semibold text-ink">{c.full_name}</p>
                  <p className="text-xs text-secondary-500">{c.admission_number}</p>
                  {c.class && <Badge variant="info" className="mt-1">{c.class}</Badge>}
                </div>
              </div>
              <div className="mt-4 flex items-center justify-between rounded-card bg-secondary-50 p-3">
                <span className="flex items-center gap-1 text-sm text-secondary-600"><Wallet className="h-4 w-4" />{ui('Solde', 'Balance')}</span>
                <span className={`font-semibold ${c.balance > 0 ? 'text-danger' : 'text-success'}`}>{formatXAF(c.balance)}</span>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
