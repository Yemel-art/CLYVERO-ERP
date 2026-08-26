'use client';

import { useCallback, useMemo, useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Mail, ArrowLeft, CheckCircle2, School, AlertCircle } from 'lucide-react';
import type { AxiosError } from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { authApi } from '@/lib/api/auth';
import { useTranslation } from '@/hooks/useTranslation';
import type { ApiError } from '@/types/api';

type Values = { email: string; school_slug?: string };

export default function ForgotPasswordPage() {
  const { language } = useTranslation();
  const ui = useCallback(
    (french: string, english: string) => language === 'fr' ? french : english,
    [language],
  );
  const schema = useMemo(() => z.object({
    email: z.string()
      .min(1, ui('Veuillez saisir votre adresse e-mail.', 'Please enter your email.'))
      .email(ui('Veuillez saisir une adresse e-mail valide.', 'Please enter a valid email.')),
    school_slug: z.string().regex(/^[a-z0-9-]*$/).optional(),
  }), [ui]);
  const [submitted, setSubmitted] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', school_slug: '' },
  });

  const onSubmit = async ({ email, school_slug }: Values) => {
    setServerError(null);
    try {
      await authApi.forgotPassword(email, school_slug);
      // The backend returns the same success response for known and unknown
      // accounts, preventing account enumeration.
      setSubmitted(true);
    } catch (error) {
      const axiosError = error as AxiosError<ApiError>;
      setServerError(axiosError.response?.status === 429
        ? ui('Trop de demandes. Veuillez patienter avant de réessayer.', 'Too many requests. Please wait before trying again.')
        : ui('Impossible d’envoyer la demande. Vérifiez votre connexion et réessayez.', 'Unable to send the request. Check your connection and try again.'));
    }
  };

  if (submitted) {
    return (
      <Card>
        <CardContent className="py-10 text-center">
          <CheckCircle2 className="mx-auto h-12 w-12 text-success" aria-hidden />
          <h2 className="mt-4 text-lg font-semibold text-ink">{ui('Consultez votre boîte de réception', 'Check your inbox')}</h2>
          <p className="mt-2 text-sm text-secondary-600">
            {ui(
              'Si un compte correspond à cette adresse, les instructions de réinitialisation ont été envoyées. Le lien est temporaire et à usage unique.',
              'If an account with that email exists, we’ve sent password reset instructions. The link is temporary and can only be used once.',
            )}
          </p>
          <Link
            href="/login"
            className="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700"
          >
            <ArrowLeft className="h-4 w-4" /> {ui('Retour à la connexion', 'Back to sign in')}
          </Link>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{ui('Réinitialisez votre mot de passe', 'Reset your password')}</CardTitle>
        <CardDescription>
          {ui('Saisissez l’adresse e-mail associée à votre compte pour recevoir un lien de réinitialisation.', 'Enter the email associated with your account and we’ll send you a reset link.')}
        </CardDescription>
      </CardHeader>
      <CardContent>
        {serverError && (
          <div role="alert" className="mb-4 flex items-start gap-2 rounded-card border border-danger/30 bg-danger-light px-3 py-2 text-sm text-danger-dark">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
            <span>{serverError}</span>
          </div>
        )}
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
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
            label={ui('Code de l’établissement', 'School code')}
            placeholder="e.g. your-school"
            leftIcon={<School className="h-4 w-4" />}
            hint={ui('Optionnel si votre adresse e-mail est unique.', 'Optional when your email is unique.')}
            error={errors.school_slug?.message}
            {...register('school_slug')}
          />
          <Button type="submit" fullWidth size="lg" isLoading={isSubmitting}>
            {ui('Envoyer le lien', 'Send reset link')}
          </Button>
          <Link
            href="/login"
            className="flex items-center justify-center gap-1.5 text-sm font-medium text-secondary-600 hover:text-primary-600"
          >
            <ArrowLeft className="h-4 w-4" /> {ui('Retour à la connexion', 'Back to sign in')}
          </Link>
        </form>
      </CardContent>
    </Card>
  );
}
