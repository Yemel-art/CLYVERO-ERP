'use client';

import { useState, useRef, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { LogOut, User as UserIcon, Settings, ChevronDown, Bell, Languages } from 'lucide-react';
import { toast } from 'sonner';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { useAuthStore } from '@/store/auth';
import { useNotifications } from '@/hooks/notifications';
import { cn } from '@/lib/utils/cn';
import { useLanguageStore } from '@/store/language';
import { useTranslation } from '@/hooks/useTranslation';
import { loginRouteFor } from '@/types/user';

export function Topbar() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);
  const toggleLanguage = useLanguageStore((s) => s.toggleLanguage);
  const { language, t } = useTranslation();
  const [open, setOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const isPlatformAdministrator = user?.role.name === 'super_administrator';

  // Lightweight notification poll for the unread badge.
  // Platform administrators are intentionally not attached to a school, so
  // do not call tenant notification endpoints from the platform shell.
  const { data: notifData } = useNotifications({ limit: 5 }, !isPlatformAdministrator);
  const unread = notifData?.unread_count ?? 0;

  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const handleLogout = async () => {
    setOpen(false);
    await logout();
    toast.success(t('You have been logged out.'));
    window.location.replace(loginRouteFor(user!.role.name));
  };

  if (!user) return null;

  // Notifications page lives under each role's section; default to admin.
  const notifPath =
    user.role.name === 'super_administrator' ? '/platform/dashboard'
    : user.role.name === 'administrator' ? '/admin/notifications'
    : user.role.name === 'secretary'   ? '/secretary/notifications'
    : user.role.name === 'teacher'     ? '/teacher/notifications'
                                       : '/parent/notifications';
  const settingsPath = user.role.name === 'super_administrator'
    ? '/platform/schools'
    : user.role.name === 'administrator' ? '/admin/settings' : '/profile';

  return (
    <header className="flex h-16 items-center justify-between border-b border-secondary-200 bg-surface px-6">
      <div /> {/* placeholder — Phase 2 will add the global search here */}

      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={toggleLanguage}
          aria-label={language === 'fr' ? 'Switch to English' : 'Passer en français'}
          className="flex items-center gap-1.5 rounded-button border border-secondary-200 px-2.5 py-1.5 text-xs font-semibold text-secondary-700 hover:bg-secondary-50"
        >
          <Languages className="h-4 w-4" />
          {language === 'fr' ? '🇫🇷 FR' : '🇬🇧 EN'}
        </button>
        {!isPlatformAdministrator && (
          <button
            aria-label={t('Notifications')}
            onClick={() => router.push(notifPath)}
            className="relative rounded-button p-2 text-secondary-500 hover:bg-secondary-100 hover:text-ink"
          >
            <Bell className="h-5 w-5" />
            {unread > 0 && (
              <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
                {unread > 99 ? '99+' : unread}
              </span>
            )}
          </button>
        )}

        <div className="relative" ref={menuRef}>
          <button
            onClick={() => setOpen((o) => !o)}
            aria-haspopup="menu"
            aria-expanded={open}
            className="flex items-center gap-2 rounded-button px-2 py-1.5 hover:bg-secondary-50"
          >
            <Avatar
              name={user.full_name}
              src={isPlatformAdministrator ? user.avatar : (user.school?.logo_url ?? user.avatar)}
              size="sm"
            />
            <div className="hidden text-left md:block">
              <p className="text-sm font-medium text-ink">{user.full_name}</p>
              <p className="text-xs text-secondary-500">{user.role.display_name}</p>
            </div>
            <ChevronDown className="h-4 w-4 text-secondary-400" />
          </button>

          <div
            role="menu"
            className={cn(
              'absolute right-0 mt-2 w-56 origin-top-right rounded-card border border-secondary-200 bg-surface shadow-card',
              open ? 'visible opacity-100' : 'invisible opacity-0',
              'transition-all duration-100',
            )}
          >
            <div className="border-b border-secondary-200 p-3">
              <p className="text-sm font-medium text-ink">{user.full_name}</p>
              <p className="truncate text-xs text-secondary-500">{user.email}</p>
              <Badge variant="default" className="mt-2">{user.role.display_name}</Badge>
            </div>
            <ul className="py-1 text-sm">
              <li>
                <button
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-secondary-700 hover:bg-secondary-50"
                  onClick={() => { setOpen(false); router.push('/profile'); }}
                  role="menuitem"
                >
                  <UserIcon className="h-4 w-4 text-secondary-400" /> {t('My profile')}
                </button>
              </li>
              <li>
                <button
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-secondary-700 hover:bg-secondary-50"
                  onClick={() => { setOpen(false); router.push(settingsPath); }}
                  role="menuitem"
                >
                  <Settings className="h-4 w-4 text-secondary-400" /> {t('Settings')}
                </button>
              </li>
              <li className="border-t border-secondary-200">
                <button
                  className="flex w-full items-center gap-2 px-3 py-2 text-left text-danger hover:bg-danger-light"
                  onClick={handleLogout}
                  role="menuitem"
                >
                  <LogOut className="h-4 w-4" /> {t('Log out')}
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </header>
  );
}
