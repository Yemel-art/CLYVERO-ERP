/**
 * User-related types. Mirror UserResource in the backend.
 */

export type UserRoleName = 'super_administrator' | 'administrator' | 'secretary' | 'teacher' | 'parent';

export interface Role {
  id: string;
  name: UserRoleName;
  display_name: string;
  permissions: string[];
}

export interface User {
  id: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string | null;
  avatar: string | null;
  is_active: boolean;
  two_factor_enabled: boolean;
  last_login_at: string | null;
  role: Role;
  school?: { id: string; name: string; slug: string; school_code: string; logo_url?: string | null; default_locale: 'en' | 'fr' } | null;
  created_at: string | null;
}

export interface AuthenticatedLoginResponse {
  requires_otp?: false;
  user: User;
  token: string;
  token_type: 'Bearer';
}

export interface LoginOtpChallenge {
  requires_otp: true;
  challenge_id: string;
  masked_email: string;
  expires_in: number;
}

export type LoginResponse = AuthenticatedLoginResponse | LoginOtpChallenge;

/**
 * Map a role to its post-login landing route.
 * Mirrors `UserRole::dashboardRoute()` on the backend.
 */
export function dashboardRouteFor(role: UserRoleName): string {
  switch (role) {
    case 'super_administrator': return '/platform/dashboard';
    case 'administrator': return '/admin/dashboard';
    case 'secretary':     return '/secretary/dashboard';
    case 'teacher':       return '/teacher/dashboard';
    case 'parent':        return '/parent/dashboard';
  }
}
