'use client';

import { create } from 'zustand';
import type { User } from '@/types/user';
import { authApi } from '@/lib/api/auth';
import { tokenStorage } from '@/lib/api/client';

interface AuthState {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isInitialized: boolean;
  setSession: (user: User, token: string, remember?: boolean) => void;
  updateUser: (user: User) => void;
  clearSession: () => void;
  hydrate: () => Promise<void>;
  logout: () => Promise<void>;
  hasPermission: (permission: string) => boolean;
}

/** Active auth state is intentionally tab-local. tokenStorage handles the
 * optional browser-level "Remember me" fallback without allowing one tab's
 * login to overwrite another tab's active identity. */
export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  token: null,
  isLoading: false,
  isInitialized: false,

  setSession: (user, token, remember = false) => {
    tokenStorage.set(token, remember);
    set({ user, token, isLoading: false, isInitialized: true });
  },

  updateUser: (user) => set({ user }),

  clearSession: () => {
    tokenStorage.clear();
    set({ user: null, token: null, isLoading: false, isInitialized: true });
  },

  hydrate: async () => {
    if (get().isInitialized) return;
    set({ isLoading: true });

    const storedToken = tokenStorage.get();
    if (!storedToken) {
      set({ isLoading: false, isInitialized: true });
      return;
    }

    try {
      const user = await authApi.me();
      set({ user, token: storedToken, isLoading: false, isInitialized: true });
    } catch {
      tokenStorage.clear();
      set({ user: null, token: null, isLoading: false, isInitialized: true });
    }
  },

  logout: async () => {
    try {
      await authApi.logout();
    } catch {
      // Best effort: local tab state must still be cleared.
    } finally {
      get().clearSession();
    }
  },

  hasPermission: (permission) => Boolean(get().user?.role.permissions.includes(permission)),
}));

if (typeof window !== 'undefined') {
  window.addEventListener('auth:unauthenticated', () => {
    useAuthStore.getState().clearSession();
  });
}
