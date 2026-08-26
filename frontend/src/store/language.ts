'use client';

import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { Language } from '@/i18n/translations';

interface LanguageState {
  language: Language;
  setLanguage: (language: Language) => void;
  toggleLanguage: () => void;
}

export const useLanguageStore = create<LanguageState>()(
  persist(
    (set, get) => ({
      language: 'fr',
      setLanguage: (language) => set({ language }),
      toggleLanguage: () => set({ language: get().language === 'fr' ? 'en' : 'fr' }),
    }),
    { name: 'clyvero.language' },
  ),
);
