'use client';

import { useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  ArrowRight,
  BarChart3,
  BookOpenCheck,
  CheckCircle2,
  ClipboardList,
  GraduationCap,
  Languages,
  ReceiptText,
  ShieldCheck,
  Sparkles,
  Users,
} from 'lucide-react';
import { useAuthStore } from '@/store/auth';
import { dashboardRouteFor, type UserRoleName } from '@/types/user';
import { Spinner } from '@/components/ui/Spinner';
import { useTranslation } from '@/hooks/useTranslation';
import { useLanguageStore } from '@/store/language';

type RoleChoice = {
  role: UserRoleName;
  label: string;
  description: string;
  icon: typeof ShieldCheck;
  accent: string;
};

export default function HomePage() {
  const router = useRouter();
  const { user, isInitialized } = useAuthStore();
  const { language } = useTranslation();
  const setLanguage = useLanguageStore((state) => state.setLanguage);
  const ui = (french: string, english: string) => language === 'fr' ? french : english;

  useEffect(() => {
    if (isInitialized && user) {
      router.replace(dashboardRouteFor(user.role.name));
    }
  }, [user, isInitialized, router]);

  if (!isInitialized || user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-secondary-950">
        <Spinner size="lg" />
      </div>
    );
  }

  const roles: RoleChoice[] = [
    {
      role: 'administrator',
      label: ui('Administrateur', 'Administrator'),
      description: ui('Pilotez l\u2019école, les utilisateurs, les finances et les paramètres.', 'Manage the school, users, finance and system settings.'),
      icon: ShieldCheck,
      accent: 'from-blue-500 to-indigo-600',
    },
    {
      role: 'secretary',
      label: ui('Secrétariat', 'Secretary'),
      description: ui('Gérez les inscriptions, paiements, documents et dossiers.', 'Handle enrollment, payments, documents and student records.'),
      icon: ClipboardList,
      accent: 'from-cyan-500 to-blue-600',
    },
    {
      role: 'teacher',
      label: ui('Enseignant', 'Teacher'),
      description: ui('Accédez aux classes, notes, présences et emplois du temps.', 'Access classes, grades, attendance and timetables.'),
      icon: BookOpenCheck,
      accent: 'from-violet-500 to-purple-600',
    },
    {
      role: 'parent',
      label: ui('Parent', 'Parent'),
      description: ui('Suivez les résultats, présences, bulletins et frais de vos enfants.', 'Follow your children\u2019s results, attendance, reports and fees.'),
      icon: Users,
      accent: 'from-emerald-500 to-teal-600',
    },
  ];

  const features = [
    {
      icon: BarChart3,
      title: ui('Suivi académique complet', 'Complete academic oversight'),
      text: ui('Notes, décisions, promotions et bulletins restent liés à chaque année scolaire.', 'Grades, decisions, promotions and report cards stay connected to every school year.'),
    },
    {
      icon: ReceiptText,
      title: ui('Finances transparentes', 'Transparent school finance'),
      text: ui('Paiements physiques, soldes, reçus et historiques sont clairement centralisés.', 'Physical payments, balances, receipts and histories are clearly centralized.'),
    },
    {
      icon: ShieldCheck,
      title: ui('Accès sécurisé par rôle', 'Secure role-based access'),
      text: ui('Chaque utilisateur ne voit que les outils et les données autorisés.', 'Every user sees only the tools and school data they are authorized to access.'),
    },
  ];

  return (
    <div className="relative min-h-screen overflow-hidden bg-secondary-950 text-white">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(37,99,235,0.30),_transparent_38%),radial-gradient(circle_at_80%_20%,_rgba(124,58,237,0.20),_transparent_32%),linear-gradient(to_bottom,_#020617,_#0f172a)]" />
      <div className="absolute -left-20 top-36 h-72 w-72 rounded-full bg-primary-500/10 blur-3xl" />
      <div className="absolute -right-24 top-12 h-96 w-96 rounded-full bg-violet-500/10 blur-3xl" />

      <header className="relative z-10 border-b border-white/10">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-8 lg:px-10">
          <Link href="/" className="flex items-center gap-3" aria-label="Clyvero ERP home">
            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-400 to-primary-700 shadow-lg shadow-primary-900/40">
              <GraduationCap className="h-6 w-6" aria-hidden />
            </span>
            <span>
              <span className="block text-base font-bold tracking-tight">Clyvero ERP</span>
              <span className="block text-[11px] uppercase tracking-[0.18em] text-blue-200/70">School intelligence</span>
            </span>
          </Link>

          <div className="flex items-center gap-2 sm:gap-4">
            <div className="flex rounded-xl border border-white/10 bg-white/5 p-1" role="group" aria-label={ui('Choisir la langue', 'Choose language')}>
              {(['fr', 'en'] as const).map((locale) => (
                <button
                  key={locale}
                  type="button"
                  onClick={() => setLanguage(locale)}
                  aria-pressed={language === locale}
                  className={`rounded-lg px-2.5 py-1.5 text-xs font-semibold transition ${language === locale ? 'bg-white text-secondary-950' : 'text-white/60 hover:text-white'}`}
                >
                  {locale.toUpperCase()}
                </button>
              ))}
            </div>
            <Link href="/login" className="hidden rounded-xl border border-white/15 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 sm:inline-flex">
              {ui('Se connecter', 'Sign in')}
            </Link>
          </div>
        </div>
      </header>

      <main className="relative z-10">
        <section className="mx-auto grid max-w-7xl gap-12 px-5 pb-20 pt-16 sm:px-8 lg:grid-cols-[1.08fr_0.92fr] lg:items-center lg:px-10 lg:pb-28 lg:pt-24">
          <div>
            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-blue-300/20 bg-blue-400/10 px-3 py-1.5 text-xs font-semibold text-blue-100">
              <Sparkles className="h-3.5 w-3.5" aria-hidden />
              {ui('Une école mieux organisée, chaque jour', 'A better-organized school, every day')}
            </div>
            <h1 className="max-w-3xl text-4xl font-bold leading-[1.08] tracking-tight sm:text-5xl lg:text-6xl">
              {ui('Toute votre école,', 'Your whole school,')}{' '}
              <span className="bg-gradient-to-r from-blue-300 via-cyan-200 to-violet-300 bg-clip-text text-transparent">
                {ui('dans un espace sécurisé.', 'in one secure workspace.')}
              </span>
            </h1>
            <p className="mt-6 max-w-2xl text-base leading-8 text-secondary-300 sm:text-lg">
              {ui(
                'Clyvero ERP relie administration, enseignants, secrétariat et familles autour des mêmes informations fiables.',
                'Clyvero ERP connects administrators, teachers, secretaries and families around the same reliable school information.',
              )}
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <a href="#access-portal" className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-500 px-5 py-3 text-sm font-semibold shadow-lg shadow-primary-950/40 transition hover:bg-primary-400">
                {ui('Choisir mon profil', 'Choose my profile')}
                <ArrowRight className="h-4 w-4" aria-hidden />
              </a>
              <Link href="/login" className="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold transition hover:bg-white/10">
                {ui('Connexion directe', 'Direct sign in')}
              </Link>
            </div>

            <div className="mt-9 flex flex-wrap gap-x-6 gap-y-3 text-sm text-secondary-300">
              {[ui('Bilingue français/anglais', 'English/French bilingual'), ui('Conçu pour le Cameroun', 'Designed for Cameroon'), ui('Données scolaires protégées', 'Protected school data')].map((item) => (
                <span key={item} className="flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 text-emerald-400" aria-hidden />
                  {item}
                </span>
              ))}
            </div>
          </div>

          <div id="access-portal" className="scroll-mt-8 rounded-[28px] border border-white/10 bg-white/[0.07] p-3 shadow-2xl shadow-black/30 backdrop-blur-xl sm:p-5">
            <div className="rounded-2xl border border-white/10 bg-secondary-950/70 p-5 sm:p-6">
              <div className="mb-5 flex items-start justify-between gap-4">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-[0.18em] text-blue-300">{ui('Portail d\u2019accès', 'Access portal')}</p>
                  <h2 className="mt-2 text-2xl font-bold">{ui('Quel est votre profil ?', 'Which profile are you using?')}</h2>
                  <p className="mt-2 text-sm leading-6 text-secondary-400">{ui('Choisissez votre espace pour continuer vers la connexion.', 'Choose your workspace to continue to secure sign in.')}</p>
                </div>
                <Languages className="hidden h-6 w-6 text-secondary-500 sm:block" aria-hidden />
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                {roles.map(({ role, label, description, icon: Icon, accent }) => (
                  <Link
                    key={role}
                    href={`/login?role=${role}`}
                    className="group rounded-2xl border border-white/10 bg-white/[0.04] p-4 transition hover:-translate-y-0.5 hover:border-blue-300/40 hover:bg-white/[0.08] focus-visible:ring-offset-secondary-950"
                  >
                    <span className={`mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br ${accent} shadow-lg`}>
                      <Icon className="h-5 w-5" aria-hidden />
                    </span>
                    <span className="flex items-center justify-between gap-3 font-semibold">
                      {label}
                      <ArrowRight className="h-4 w-4 text-secondary-500 transition group-hover:translate-x-1 group-hover:text-blue-300" aria-hidden />
                    </span>
                    <span className="mt-1.5 block text-xs leading-5 text-secondary-400">{description}</span>
                  </Link>
                ))}
              </div>
            </div>
          </div>
        </section>

        <section className="border-y border-white/10 bg-white/[0.035]">
          <div className="mx-auto grid max-w-7xl grid-cols-2 divide-x divide-white/10 px-5 sm:px-8 lg:grid-cols-4 lg:px-10">
            {[
              ['4', ui('espaces par rôle', 'role-based workspaces')],
              ['2', ui('langues intégrées', 'built-in languages')],
              ['100%', ui('historique conservé', 'academic history retained')],
              ['24/7', ui('accès sécurisé', 'secure availability')],
            ].map(([value, label]) => (
              <div key={label} className="px-4 py-7 text-center">
                <p className="text-2xl font-bold text-white">{value}</p>
                <p className="mt-1 text-xs uppercase tracking-wide text-secondary-400">{label}</p>
              </div>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:px-10 lg:py-24">
          <div className="mx-auto max-w-2xl text-center">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-blue-300">{ui('Un système cohérent', 'One connected system')}</p>
            <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{ui('Moins de confusion. Plus de contrôle.', 'Less confusion. More control.')}</h2>
            <p className="mt-4 leading-7 text-secondary-400">{ui('Les opérations importantes restent traçables, structurées et accessibles aux bonnes personnes.', 'Important school operations remain traceable, structured and available to the right people.')}</p>
          </div>

          <div className="mt-12 grid gap-5 md:grid-cols-3">
            {features.map(({ icon: Icon, title, text }) => (
              <article key={title} className="rounded-2xl border border-white/10 bg-white/[0.045] p-6">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-500/15 text-blue-300">
                  <Icon className="h-5 w-5" aria-hidden />
                </span>
                <h3 className="mt-5 text-lg font-semibold">{title}</h3>
                <p className="mt-2 text-sm leading-6 text-secondary-400">{text}</p>
              </article>
            ))}
          </div>
        </section>
      </main>

      <footer className="relative z-10 border-t border-white/10">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-6 text-xs text-secondary-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10">
          <p>© {new Date().getFullYear()} Clyvero ERP. {ui('Gestion scolaire sécurisée.', 'Secure school management.')}</p>
          <p>{ui('Conçu pour les écoles qui veulent avancer.', 'Built for schools ready to move forward.')}</p>
        </div>
      </footer>
    </div>
  );
}
