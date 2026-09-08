'use client';

import { Suspense, useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Eye, EyeOff, Mail, Lock, AlertCircle, RefreshCw, School, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { authApi } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { dashboardRouteFor } from '@/types/user';
import type { ApiError } from '@/types/api';
import type { AxiosError } from 'axios';
import { useTranslation } from '@/hooks/useTranslation';
import type { LoginOtpChallenge, LoginResponse } from '@/types/user';

function isOtpChallenge(result: LoginResponse): result is LoginOtpChallenge {
  return result.requires_otp === true
    || ('challenge_id' in result && typeof result.challenge_id === 'string');
}

const loginSchema = z.object({
  email: z.string().min(1).email(),
  password: z.string().min(1),
  school_slug: z.string().optional(),
  remember_me: z.boolean().optional(),
});

type LoginValues = z.infer<typeof loginSchema>;

function LoginForm() {
  const { language } = useTranslation();
  const ui = useCallback(
    (french: string, english: string) => language === 'fr' ? french : english,
    [language],
  );
  const localizedSchema = useMemo(() => z.object({
    email: z.string()
      .min(1, ui('Veuillez saisir votre adresse e-mail.', 'Please enter your email.'))
      .email(ui('Veuillez saisir une adresse e-mail valide.', 'Please enter a valid email.')),
    password: z.string().min(1, ui('Veuillez saisir votre mot de passe.', 'Please enter your password.')),
    school_slug: z.string().regex(/^[A-Za-z0-9-]*$/, ui('Utilisez uniquement des lettres, chiffres et tirets.', 'Use letters, numbers, and hyphens only.')).optional(),
    remember_me: z.boolean().optional(),
  }), [ui]);
  const router = useRouter();
  const searchParams = useSearchParams();
  const roleParam = searchParams.get('role');
  const roleCopy: Record<string, { fr: string; en: string }> = {
    platform: { fr: 'propriétaire CLYVERO', en: 'CLYVERO owner' },
    administrator: { fr: 'administrateur', en: 'Administrator' },
    secretary: { fr: 'secrétariat', en: 'Secretary' },
    teacher: { fr: 'enseignant', en: 'Teacher' },
    parent: { fr: 'parent', en: 'Parent' },
  };
  const selectedRole = roleParam ? roleCopy[roleParam] : undefined;
  const isPlatformAccess = roleParam === 'platform';
  const requestedNextPath = searchParams.get('next');
  const nextPath = requestedNextPath?.startsWith('/') && !requestedNextPath.startsWith('//')
    ? requestedNextPath
    : null;
  const { user, isInitialized, setSession } = useAuthStore();
  const [showPassword, setShowPassword] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [otpChallenge, setOtpChallenge] = useState<LoginOtpChallenge | null>(null);
  const [rememberForChallenge, setRememberForChallenge] = useState(false);
  const [otpCode, setOtpCode] = useState('');
  const [isVerifyingOtp, setIsVerifyingOtp] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(localizedSchema),
    defaultValues: { email: '', password: '', school_slug: '', remember_me: false },
  });

  // Already authenticated? Bounce to dashboard.
  useEffect(() => {
    if (isInitialized && user) {
      router.replace(nextPath || dashboardRouteFor(user.role.name));
    }
  }, [isInitialized, user, nextPath, router]);

  const onSubmit = async (values: LoginValues) => {
    setServerError(null);
    try {
      const result = await authApi.login(values.email, values.password, values.remember_me ?? false, values.school_slug);
      if (isOtpChallenge(result)) {
        setRememberForChallenge(values.remember_me ?? false);
        setOtpChallenge(result);
        setOtpCode('');
        toast.success(ui('Code de vérification envoyé.', 'Verification code sent.'));
        return;
      }
      if (!result.user || !result.token) {
        throw new Error(ui(
          'Réponse de connexion inattendue. Actualisez la page et réessayez.',
          'Unexpected login response. Refresh the page and try again.',
        ));
      }
      setSession(result.user, result.token, values.remember_me ?? false);
      toast.success(ui(`Bon retour, ${result.user.first_name}.`, `Welcome back, ${result.user.first_name}.`));
      // A full navigation rebuilds the authenticated tree from the token that
      // was just persisted. This avoids a race between router navigation,
      // query-cache cleanup and AuthGuard hydration after a fresh login.
      window.location.replace(nextPath || dashboardRouteFor(result.user.role.name));
    } catch (err) {
      const axiosErr = err as AxiosError<ApiError>;
      const message = axiosErr.response?.data?.message
        ?? (err instanceof Error ? err.message : ui('Échec de connexion. Veuillez réessayer.', 'Login failed. Please try again.'));
      setServerError(message);
    }
  };

  const verifyOtp = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!otpChallenge || !/^\d{6}$/.test(otpCode)) {
      setServerError(ui('Saisissez le code à 6 chiffres.', 'Enter the 6-digit code.'));
      return;
    }

    setServerError(null);
    setIsVerifyingOtp(true);
    try {
      const result = await authApi.verifyLoginOtp(otpChallenge.challenge_id, otpCode);
      if (!result.user || !result.token) {
        throw new Error(ui(
          'Réponse de vérification inattendue. Recommencez la connexion.',
          'Unexpected verification response. Please sign in again.',
        ));
      }
      setSession(result.user, result.token, rememberForChallenge);
      toast.success(ui(`Bon retour, ${result.user.first_name}.`, `Welcome back, ${result.user.first_name}.`));
      window.location.replace(nextPath || dashboardRouteFor(result.user.role.name));
    } catch (err) {
      const axiosErr = err as AxiosError<ApiError>;
      const message = axiosErr.response?.data?.errors?.code?.[0]
        ?? axiosErr.response?.data?.message
        ?? ui('Code invalide ou expiré.', 'Invalid or expired code.');
      setServerError(message);
    } finally {
      setIsVerifyingOtp(false);
    }
  };

  if (otpChallenge) {
    return (
      <Card>
        <CardHeader>
          <div className="mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-primary-50 text-primary-600">
            <ShieldCheck className="h-6 w-6" />
          </div>
          <CardTitle>{ui('Vérification administrateur', 'Administrator verification')}</CardTitle>
          <CardDescription>
            {ui(
              `Un code à 6 chiffres a été envoyé à ${otpChallenge.masked_email}.`,
              `A 6-digit code was sent to ${otpChallenge.masked_email}.`,
            )}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {serverError && (
            <div role="alert" className="mb-4 flex items-start gap-2 rounded-card border border-danger/30 bg-danger-light px-3 py-2 text-sm text-danger-dark">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              <span>{serverError}</span>
            </div>
          )}

          <form key="administrator-otp-form" onSubmit={verifyOtp} className="space-y-4" noValidate>
            <Input
              label={ui('Code de vérification', 'Verification code')}
              value={otpCode}
              onChange={(event) => setOtpCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
              inputMode="numeric"
              autoComplete="one-time-code"
              placeholder="000000"
              maxLength={6}
              leftIcon={<ShieldCheck className="h-4 w-4" />}
              hint={ui('Le code expire dans 10 minutes et ne fonctionne qu’une seule fois.', 'The code expires in 10 minutes and can only be used once.')}
              required
            />
            <Button type="submit" fullWidth size="lg" isLoading={isVerifyingOtp} disabled={otpCode.length !== 6}>
              {ui('Vérifier et se connecter', 'Verify and sign in')}
            </Button>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <Button
                type="button"
                variant="outline"
                leftIcon={<RefreshCw className="h-4 w-4" />}
                onClick={() => void handleSubmit(onSubmit)()}
                isLoading={isSubmitting}
              >
                {ui('Renvoyer le code', 'Send a new code')}
              </Button>
              <Button
                type="button"
                variant="ghost"
                leftIcon={<ArrowLeft className="h-4 w-4" />}
                onClick={() => { setOtpChallenge(null); setOtpCode(''); setRememberForChallenge(false); setServerError(null); }}
              >
                {ui('Modifier les identifiants', 'Change credentials')}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <Link href="/" className="mb-2 inline-flex w-fit items-center gap-1.5 text-xs font-medium text-secondary-500 hover:text-primary-700">
          <ArrowLeft className="h-3.5 w-3.5" aria-hidden />
          {ui('Retour à l’accueil', 'Back to welcome page')}
        </Link>
        <CardTitle>
          {selectedRole
            ? ui(`Connexion ${selectedRole.fr}`, `${selectedRole.en} sign in`)
            : ui('Connectez-vous à votre compte', 'Sign in to your account')}
        </CardTitle>
        <CardDescription>
          {isPlatformAccess
            ? ui('Accès privé réservé au propriétaire de la plateforme CLYVERO.', 'Private access reserved for the CLYVERO platform owner.')
            : ui('Saisissez les identifiants fournis par votre établissement.', 'Enter the credentials provided by your school.')}
        </CardDescription>
      </CardHeader>
      <CardContent>
        {serverError && (
          <div
            role="alert"
            className="mb-4 flex items-start gap-2 rounded-card border border-danger/30 bg-danger-light px-3 py-2 text-sm text-danger-dark"
          >
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
            <span>{serverError}</span>
          </div>
        )}

        <form key="credentials-form" onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          {!isPlatformAccess && <Input
              label={ui('Code de l’établissement', 'School code')}
              placeholder="e.g. SCH-AB12CD34"
              autoComplete="organization"
              leftIcon={<School className="h-4 w-4" />}
              hint={ui(
                'Ce code est fourni par votre école. L’administrateur peut le consulter dans Paramètres.',
                'Your school provides this code. Administrators can view it under Settings.',
              )}
              error={errors.school_slug?.message}
              {...register('school_slug')}
            />}
          <Input
            type="email"
            label={ui('Adresse e-mail', 'Email')}
            placeholder="you@example.test"
            autoComplete="email"
            leftIcon={<Mail className="h-4 w-4" />}
            error={errors.email?.message}
            required
            {...register('email')}
          />

          <Input
            type={showPassword ? 'text' : 'password'}
            label={ui('Mot de passe', 'Password')}
            placeholder="••••••••••••"
            autoComplete="current-password"
            leftIcon={<Lock className="h-4 w-4" />}
            rightIcon={
              <button
                type="button"
                onClick={() => setShowPassword((s) => !s)}
                aria-label={showPassword ? ui('Masquer le mot de passe', 'Hide password') : ui('Afficher le mot de passe', 'Show password')}
                className="pointer-events-auto text-secondary-400 hover:text-secondary-700"
              >
                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            }
            error={errors.password?.message}
            required
            {...register('password')}
          />

          <div className="flex items-center justify-between text-sm">
            <label className="flex items-center gap-2 text-secondary-600">
              <input
                type="checkbox"
                className="rounded border-secondary-300 text-primary-600 focus:ring-primary-500"
                {...register('remember_me')}
              />
              {ui('Se souvenir de moi', 'Remember me')}
            </label>
            <Link href={isPlatformAccess ? '/forgot-password?scope=platform' : '/forgot-password'} className="font-medium text-primary-600 hover:text-primary-700">
              {ui('Mot de passe oublié ?', 'Forgot password?')}
            </Link>
          </div>

          <Button type="submit" fullWidth size="lg" isLoading={isSubmitting}>
            {ui('Se connecter', 'Sign in')}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

export default function LoginPage() {
  return (
    <Suspense fallback={<Card><CardContent className="h-80 animate-pulse bg-secondary-50" /></Card>}>
      <LoginForm />
    </Suspense>
  );
}
