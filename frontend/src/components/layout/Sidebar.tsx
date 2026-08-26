'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { GraduationCap } from 'lucide-react';
import { useAuthStore } from '@/store/auth';
import { NAV_MAP } from '@/config/navigation';
import { cn } from '@/lib/utils/cn';
import { useTranslation } from '@/hooks/useTranslation';

export function Sidebar() {
  const pathname = usePathname();
  const user = useAuthStore((state) => state.user);
  const hasPermission = useAuthStore((state) => state.hasPermission);
  const { t } = useTranslation();

  if (!user) return null;

  const groups = NAV_MAP[user.role.name];
  const isPlatformOwner = user.role.name === 'super_administrator';
  const school = isPlatformOwner ? null : user.school;

  return (
    <aside className="hidden h-screen w-64 shrink-0 border-r border-secondary-200 bg-surface lg:flex lg:flex-col">
      <div className="flex h-16 items-center gap-2 border-b border-secondary-200 px-4">
        {school?.logo_url ? (
          <img src={school.logo_url} alt="" className="h-10 w-10 shrink-0 rounded object-contain" />
        ) : (
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-button bg-primary-600 text-white">
            <GraduationCap className="h-5 w-5" />
          </div>
        )}
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-ink">{school?.name ?? 'Clyvero ERP'}</p>
          <p className="truncate text-xs text-secondary-500">{school ? school.school_code : t('Platform owner')}</p>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 py-4">
        {groups.map((group) => {
          const visibleItems = group.items.filter((item) => !item.permission || hasPermission(item.permission));
          if (visibleItems.length === 0) return null;

          return (
            <div key={group.label} className="mb-6">
              <p className="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-secondary-400">{t(group.label)}</p>
              <ul className="space-y-0.5">
                {visibleItems.map((item) => {
                  const isActive = pathname === item.href || pathname.startsWith(`${item.href}/`);
                  return <li key={item.href}><Link href={item.href} className={cn(
                    'flex items-center gap-3 rounded-button px-3 py-2 text-sm font-medium transition-colors',
                    isActive ? 'bg-primary-50 text-primary-700' : 'text-secondary-600 hover:bg-secondary-50 hover:text-ink',
                  )}><item.icon className={cn('h-4 w-4', isActive ? 'text-primary-600' : 'text-secondary-400')} aria-hidden /><span>{t(item.label)}</span></Link></li>;
                })}
              </ul>
            </div>
          );
        })}
      </nav>

      <div className="border-t border-secondary-200 p-4 text-xs text-secondary-500">v2.0.0 · {t('Secure school workspace')}</div>
    </aside>
  );
}
