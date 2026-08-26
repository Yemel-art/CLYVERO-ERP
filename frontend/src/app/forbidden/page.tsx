'use client';

import Link from 'next/link';
import { ShieldOff } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { useAuthStore } from '@/store/auth';
import { dashboardRouteFor } from '@/types/user';
import { useTranslation } from '@/hooks/useTranslation';

export default function ForbiddenPage() {
  const user = useAuthStore((s) => s.user);
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const dashboardHref = user ? dashboardRouteFor(user.role.name) : '/login';

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4 text-center">
      <ShieldOff className="h-16 w-16 text-danger" aria-hidden />
      <h1 className="mt-6 text-2xl font-semibold text-ink">{ui('Accès refusé', 'Access denied')}</h1>
      <p className="mt-2 max-w-md text-sm text-secondary-600">
        {ui(
          'Vous n’avez pas l’autorisation de consulter cette page. Si vous pensez qu’il s’agit d’une erreur, contactez votre administrateur système.',
          'You don’t have permission to view this page. If you believe this is an error, contact your system administrator.',
        )}
      </p>
      <Link href={dashboardHref}>
        <Button className="mt-6">{ui('Retour au tableau de bord', 'Return to dashboard')}</Button>
      </Link>
    </div>
  );
}
