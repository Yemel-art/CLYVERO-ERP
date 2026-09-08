import axios, { type AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';
import type { ApiError, ApiResponse } from '@/types/api';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE ?? 'http://localhost/api/v1';
const TAB_TOKEN_KEY = 'clyvero.auth.tab-token';
const TAB_RESOLVED_KEY = 'clyvero.auth.tab-resolved';
const REMEMBERED_TOKEN_KEY = 'clyvero.auth.remembered-token';
const LEGACY_TOKEN_KEY = 'clyvero.auth.token';
const LEGACY_STORE_KEY = 'clyvero.auth';

/**
 * Authentication storage policy:
 * - The active bearer token is kept in sessionStorage, which is isolated per
 *   browser tab. An admin tab and teacher tab can therefore coexist.
 * - "Remember me" keeps one optional fallback token in localStorage. A tab
 *   adopts that token only once, during its first hydration.
 * - Legacy browser-wide tokens are migrated once into the current tab.
 */
export const tokenStorage = {
  get(): string | null {
    if (typeof window === 'undefined') return null;

    const activeToken = window.sessionStorage.getItem(TAB_TOKEN_KEY);
    if (activeToken) return activeToken;
    if (window.sessionStorage.getItem(TAB_RESOLVED_KEY) === '1') return null;

    window.sessionStorage.setItem(TAB_RESOLVED_KEY, '1');

    // Opening the login screen explicitly means "use another account in this
    // tab"; do not silently restore a remembered account here.
    if (window.location.pathname === '/login') return null;

    const legacyToken = window.localStorage.getItem(LEGACY_TOKEN_KEY);
    if (legacyToken) {
      window.sessionStorage.setItem(TAB_TOKEN_KEY, legacyToken);
      window.localStorage.setItem(REMEMBERED_TOKEN_KEY, legacyToken);
      window.localStorage.removeItem(LEGACY_TOKEN_KEY);
      window.localStorage.removeItem(LEGACY_STORE_KEY);
      return legacyToken;
    }

    const rememberedToken = window.localStorage.getItem(REMEMBERED_TOKEN_KEY);
    if (rememberedToken) {
      window.sessionStorage.setItem(TAB_TOKEN_KEY, rememberedToken);
      return rememberedToken;
    }

    return null;
  },

  set(token: string, remember = false): void {
    if (typeof window === 'undefined') return;
    window.sessionStorage.setItem(TAB_TOKEN_KEY, token);
    window.sessionStorage.setItem(TAB_RESOLVED_KEY, '1');
    window.localStorage.removeItem(LEGACY_TOKEN_KEY);
    window.localStorage.removeItem(LEGACY_STORE_KEY);
    if (remember) window.localStorage.setItem(REMEMBERED_TOKEN_KEY, token);
  },

  clear(): void {
    if (typeof window === 'undefined') return;
    const activeToken = window.sessionStorage.getItem(TAB_TOKEN_KEY);
    window.sessionStorage.removeItem(TAB_TOKEN_KEY);
    window.sessionStorage.setItem(TAB_RESOLVED_KEY, '1');
    window.localStorage.removeItem(LEGACY_TOKEN_KEY);
    window.localStorage.removeItem(LEGACY_STORE_KEY);
    if (activeToken && window.localStorage.getItem(REMEMBERED_TOKEN_KEY) === activeToken) {
      window.localStorage.removeItem(REMEMBERED_TOKEN_KEY);
    }
  },
};

function createClient(): AxiosInstance {
  const instance = axios.create({
    baseURL: API_BASE_URL,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    withCredentials: false,
  });

  instance.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    if (typeof FormData !== 'undefined' && config.data instanceof FormData) {
      config.headers.delete('Content-Type');
    }
    if (typeof window !== 'undefined') {
      const token = tokenStorage.get();
      if (token) config.headers.Authorization = `Bearer ${token}`;
      const languageState = window.localStorage.getItem('clyvero.language');
      if (languageState) {
        try {
          const language = JSON.parse(languageState)?.state?.language;
          if (language === 'en' || language === 'fr') config.headers['X-Locale'] = language;
        } catch {
          // Ignore malformed legacy preference data.
        }
      }
    }
    return config;
  });

  instance.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiError>) => {
      if (error.response?.status === 401 && typeof window !== 'undefined') {
        tokenStorage.clear();
        window.dispatchEvent(new CustomEvent('auth:unauthenticated'));
      }
      return Promise.reject(error);
    },
  );

  return instance;
}

export const apiClient = createClient();

export function unwrap<T>(response: ApiResponse<T>): T {
  if (!response.success) {
    const err = new Error(response.message) as Error & { code?: string; errors?: ApiError['errors'] };
    err.code = response.error_code;
    err.errors = response.errors;
    throw err;
  }
  return response.data as T;
}
