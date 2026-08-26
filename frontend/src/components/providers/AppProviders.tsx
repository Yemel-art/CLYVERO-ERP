'use client';

import { useEffect, useRef, useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'sonner';
import { useAuthStore } from '@/store/auth';
import { useLanguageStore } from '@/store/language';
import { RuntimeTranslator } from '@/components/providers/RuntimeTranslator';

/**
 * Root client-side providers:
 *   - TanStack Query for server state.
 *   - Sonner for top-right toasts (5s auto-dismiss per Design Philosophy).
 *   - Auth store hydration on mount.
 */
export function AppProviders({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60 * 1000,
            refetchOnWindowFocus: false,
            retry: 1,
          },
        },
      }),
  );

  const hydrate = useAuthStore((s) => s.hydrate);
  const token = useAuthStore((s) => s.token);
  const language = useLanguageStore((s) => s.language);
  const previousToken = useRef(token);
  useEffect(() => {
    void hydrate();
  }, [hydrate]);

  useEffect(() => {
    document.documentElement.lang = language;
  }, [language]);

  // Server data belongs to the authenticated account. Never allow data cached
  // for an administrator to appear after this tab signs in as a teacher (or
  // vice versa).
  useEffect(() => {
    if (previousToken.current !== token) {
      queryClient.clear();
      previousToken.current = token;
    }
  }, [queryClient, token]);

  return (
    <QueryClientProvider client={queryClient}>
      <RuntimeTranslator />
      {children}
      <Toaster
        position="top-right"
        duration={5000}
        closeButton
        richColors
        toastOptions={{
          classNames: {
            toast: 'rounded-card shadow-card border border-secondary-200',
          },
        }}
      />
    </QueryClientProvider>
  );
}
