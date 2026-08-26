'use client';

import { GraduationCap } from 'lucide-react';
import { useTranslation } from '@/hooks/useTranslation';
import { useLanguageStore } from '@/store/language';

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  const { language } = useTranslation();
  const setLanguage = useLanguageStore((state) => state.setLanguage);
  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-primary-50 via-background to-secondary-50 px-4 py-12">
      <div className="absolute right-4 top-4 flex rounded-button border border-secondary-200 bg-surface p-1 shadow-card"
        role="group" aria-label={language === 'fr' ? 'Choisir la langue' : 'Choose language'}>
        <button type="button" onClick={() => setLanguage('fr')} aria-pressed={language === 'fr'}
          className={`rounded-button px-2 py-1 text-xs font-medium ${language === 'fr' ? 'bg-primary-600 text-white' : 'text-secondary-600'}`}>
          FR
        </button>
        <button type="button" onClick={() => setLanguage('en')} aria-pressed={language === 'en'}
          className={`rounded-button px-2 py-1 text-xs font-medium ${language === 'en' ? 'bg-primary-600 text-white' : 'text-secondary-600'}`}>
          EN
        </button>
      </div>
      <div className="mb-8 flex items-center gap-3">
        <div className="flex h-12 w-12 items-center justify-center rounded-card bg-primary-600 text-white shadow-card">
          <GraduationCap className="h-6 w-6" />
        </div>
        <div>
          <h1 className="text-xl font-semibold text-ink">Clyvero ERP</h1>
          <p className="text-xs text-secondary-500">
            {language === 'fr' ? 'Système de gestion scolaire' : 'School Management System'}
          </p>
        </div>
      </div>
      <main className="w-full max-w-md">{children}</main>
      <p className="mt-8 text-xs text-secondary-500">
        © {new Date().getFullYear()} Clyvero ERP · {language === 'fr' ? 'Espace scolaire sécurisé' : 'Secure school workspace'}
      </p>
    </div>
  );
}
