'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { NAV_MAP } from '@/config/navigation';
import { useAuthStore } from '@/store/auth';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils/cn';

export function MobileNavigation() {
  const pathname = usePathname();
  const user = useAuthStore((state) => state.user);
  const hasPermission = useAuthStore((state) => state.hasPermission);
  const { t } = useTranslation();
  if (!user) return null;

  const items = NAV_MAP[user.role.name]
    .flatMap((group) => group.items)
    .filter((item) => !item.permission || hasPermission(item.permission));

  return (
    <nav aria-label="Mobile navigation"
      className="fixed inset-x-0 bottom-0 z-40 border-t border-secondary-200 bg-surface/95 px-2 pb-[env(safe-area-inset-bottom)] shadow-lg backdrop-blur lg:hidden">
      <div className="flex gap-1 overflow-x-auto py-1.5">
        {items.map((item) => {
          const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
          return (
            <Link key={item.href} href={item.href}
              className={cn('flex min-w-[4.5rem] flex-1 flex-col items-center gap-1 rounded-button px-2 py-1.5 text-[11px] font-medium',
                active ? 'bg-primary-50 text-primary-700' : 'text-secondary-600')}>
              <item.icon className="h-4 w-4" />
              <span className="max-w-20 truncate">{t(item.label)}</span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
