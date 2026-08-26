import type { Metadata, Viewport } from 'next';
import { AppProviders } from '@/components/providers/AppProviders';
import { AppWatermark } from '@/components/layout/AppWatermark';
import './globals.css';

export const metadata: Metadata = {
  title: {
    template: '%s · Clyvero ERP',
    default: 'Clyvero ERP — Gestion scolaire',
  },
  description: 'Un ERP bilingue de gestion scolaire conçu pour les établissements modernes.',
  robots: { index: false, follow: false }, // Internal app, never indexed.
};

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  themeColor: '#2563eb',
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="fr">
      <body className="min-h-screen bg-background text-ink">
        <AppWatermark />
        <AppProviders>{children}</AppProviders>
      </body>
    </html>
  );
}
