'use client';

import { useState, Suspense } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Lock, Eye, EyeOff, AlertCircle, CheckCircle2 } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { authApi } from '@/lib/api/auth';
import type { ApiError } from '@/types/api';
import type { AxiosError } from 'axios';
import { useTranslation } from '@/hooks/useTranslation';

// Mirrors backend StrongPassword rule.
const strongPassword = z
  .string()
  .min(12, 'Password must be at least 12 characters.')
  .regex(/[A-Z]/, 'Must contain an uppercase letter.')
  .regex(/[a-z]/, 'Must contain a lowercase letter.')
  .regex(/\d/, 'Must contain a number.')
  .regex(/[^A-Za-z0-9]/, 'Must contain a special character.');

const schema = z
  .object({
    password:              strongPassword,
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    message: 'Passwords do not match.',
    path: ['password_confirmation'],
  });

type Values = z.infer<typeof schema>;

function ResetPasswordForm() {
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const router = useRouter();
  const search = useSearchParams();
  const token = search.get('token') ?? '';
  const email = search.get('email') ?? '';
  const schoolSlug = search.get('school') ?? undefined;
  const isPlatformReset = search.get('scope') === 'platform';
  const forgotPasswordHref = isPlatformReset ? '/forgot-password?scope=platform' : '/forgot-password';
  const loginHref = isPlatformReset ? '/owner/login' : '/login';

  const [showPwd, setShowPwd] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { password: '', password_confirmation: '' },
  });

  if (!token || !email) {
    return (
      <Card>
        <CardContent className="py-10 text-center">
          <AlertCircle className="mx-auto h-12 w-12 text-danger" aria-hidden />
          <h2 className="mt-4 text-lg font-semibold text-ink">{ui('Lien de réinitialisation invalide', 'Invalid reset link')}</h2>
          <p className="mt-2 text-sm text-secondary-600">
            {ui('Ce lien est incomplet. Veuillez demander un nouveau lien.', 'This password reset link is incomplete. Please request a new one.')}
          </p>
          <Link
            href={forgotPasswordHref}
            className="mt-6 inline-block text-sm font-medium text-primary-600 hover:text-primary-700"
          >
            {ui('Demander un nouveau lien', 'Request a new link')}
          </Link>
        </CardContent>
      </Card>
    );
  }

  const onSubmit = async (values: Values) => {
    setServerError(null);
    try {
      await authApi.resetPassword({
        token,
        email,
        password: values.password,
        passwordConfirmation: values.password_confirmation,
        schoolSlug,
        accountScope: isPlatformReset ? 'platform' : 'school',
      });
      toast.success(ui('Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.', 'Your password has been reset. Please sign in.'));
      router.replace(loginHref);
    } catch (err) {
      const axiosErr = err as AxiosError<ApiError>;
      const message = axiosErr.response?.data?.message
        ?? ui('Impossible de réinitialiser le mot de passe. Le lien a peut-être expiré.', 'We could not reset your password. The link may have expired.');
      setServerError(message);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>{ui('Choisissez un nouveau mot de passe', 'Choose a new password')}</CardTitle>
        <CardDescription>
          {ui('Réinitialisation du mot de passe pour', 'Resetting password for')} <span className="font-medium text-ink">{email}</span>.
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

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <Input
            type={showPwd ? 'text' : 'password'}
            label={ui('Nouveau mot de passe', 'New password')}
            placeholder={ui('Au moins 12 caractères', 'At least 12 characters')}
            autoComplete="new-password"
            leftIcon={<Lock className="h-4 w-4" />}
            rightIcon={
              <button
                type="button"
                onClick={() => setShowPwd((s) => !s)}
                aria-label={showPwd ? ui('Masquer le mot de passe', 'Hide password') : ui('Afficher le mot de passe', 'Show password')}
                className="pointer-events-auto text-secondary-400 hover:text-secondary-700"
              >
                {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            }
            error={errors.password?.message}
            hint={ui('12 caractères minimum, avec majuscule, minuscule, chiffre et caractère spécial.', '12+ chars, upper, lower, number, and special character.')}
            required
            {...register('password')}
          />
          <Input
            type={showPwd ? 'text' : 'password'}
            label={ui('Confirmer le nouveau mot de passe', 'Confirm new password')}
            autoComplete="new-password"
            leftIcon={<CheckCircle2 className="h-4 w-4" />}
            error={errors.password_confirmation?.message}
            required
            {...register('password_confirmation')}
          />
          <Button type="submit" fullWidth size="lg" isLoading={isSubmitting}>
            {ui('Réinitialiser le mot de passe', 'Reset password')}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={null}>
      <ResetPasswordForm />
    </Suspense>
  );
}
