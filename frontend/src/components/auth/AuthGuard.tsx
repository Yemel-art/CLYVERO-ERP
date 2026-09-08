'use client';

import { useEffect } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { useAuthStore } from '@/store/auth';
import { Spinner } from '@/components/ui/Spinner';
import { dashboardRouteFor, type UserRoleName } from '@/types/user';

interface AuthGuardProps {
  children: React.ReactNode;
  /** If provided, only users with one of these roles can see the page. */
  allowedRoles?: UserRoleName[];
}

/**
 * Wraps protected pages. Shows a spinner while hydrating, redirects to
 * /login if unauthenticated, redirects to the user's own dashboard if
 * their role isn't in `allowedRoles`.
 */
export function AuthGuard({ children, allowedRoles }: AuthGuardProps) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, isInitialized, isLoading } = useAuthStore();
  const routeRole: UserRoleName | null = pathname.startsWith('/platform')
    ? 'super_administrator'
    : pathname.startsWith('/admin')
    ? 'administrator'
    : pathname.startsWith('/secretary')
      ? 'secretary'
      : pathname.startsWith('/teacher')
        ? 'teacher'
        : pathname.startsWith('/parent')
          ? 'parent'
          : null;
  const effectiveAllowedRoles = allowedRoles ?? (routeRole ? [routeRole] : undefined);
  const isRoleAllowed = !effectiveAllowedRoles || !user
    || effectiveAllowedRoles.includes(user.role.name);

  useEffect(() => {
    if (!isInitialized || isLoading) return;

    if (!user) {
      const next = encodeURIComponent(pathname);
      router.replace(pathname.startsWith('/platform')
        ? '/owner/login'
        : `/login?next=${next}`);
      return;
    }

    if (!isRoleAllowed) {
      router.replace(dashboardRouteFor(user.role.name));
    }
  }, [user, isInitialized, isLoading, isRoleAllowed, pathname, router]);

  if (!isInitialized || isLoading || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!isRoleAllowed) {
    return null; // The effect will redirect.
  }

  return <>{children}</>;
}
