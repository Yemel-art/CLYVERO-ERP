'use client';

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Eye, EyeOff, ShieldCheck, UserRound } from 'lucide-react';
import { authApi } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import { loginRouteFor } from '@/types/user';
import { Avatar } from '@/components/ui/Avatar';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { useTranslation } from '@/hooks/useTranslation';

function isStrongPassword(value: string): boolean {
  return value.length >= 12
    && /[A-Z]/.test(value)
    && /[a-z]/.test(value)
    && /\d/.test(value)
    && /[^A-Za-z0-9]/.test(value);
}

export default function ProfilePage() {
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const user = useAuthStore((state) => state.user);
  const updateUser = useAuthStore((state) => state.updateUser);
  const clearSession = useAuthStore((state) => state.clearSession);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [emailCurrentPassword, setEmailCurrentPassword] = useState('');
  const [phone, setPhone] = useState('');
  const [saving, setSaving] = useState(false);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPasswords, setShowPasswords] = useState(false);
  const [changingPassword, setChangingPassword] = useState(false);

  useEffect(() => {
    if (!user) return;
    setFirstName(user.first_name);
    setLastName(user.last_name);
    setEmail(user.email);
    setPhone(user.phone ?? '');
  }, [user]);

  if (!user) return null;

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!user) return;
    if (!firstName.trim() || !lastName.trim()) {
      toast.error('First name and last name are required.');
      return;
    }
    const normalizedEmail = email.trim().toLowerCase();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail)) {
      toast.error(ui('Saisissez une adresse e-mail valide.', 'Enter a valid email address.'));
      return;
    }
    const emailChanged = normalizedEmail !== user.email.toLowerCase();
    if (emailChanged && !emailCurrentPassword) {
      toast.error(ui(
        'Saisissez votre mot de passe actuel pour modifier l’adresse e-mail.',
        'Enter your current password to change the email address.',
      ));
      return;
    }
    if (emailChanged && !window.confirm(ui(
      `Remplacer votre adresse de connexion par ${normalizedEmail} ?`,
      `Change your login email to ${normalizedEmail}?`,
    ))) return;

    setSaving(true);
    try {
      const updated = await authApi.updateProfile({
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: normalizedEmail,
        phone: phone.trim() || null,
        current_password: emailChanged ? emailCurrentPassword : undefined,
      });
      updateUser(updated);
      setEmail(updated.email);
      setEmailCurrentPassword('');
      toast.success(emailChanged
        ? ui('Adresse e-mail de connexion modifiée.', 'Login email changed successfully.')
        : ui('Profil mis à jour.', 'Profile updated successfully.'));
    } catch (error: any) {
      const validationErrors = error?.response?.data?.errors;
      toast.error(validationErrors?.current_password?.[0]
        ?? validationErrors?.email?.[0]
        ?? error?.response?.data?.message
        ?? ui('Impossible de mettre à jour le profil.', 'Unable to update the profile.'));
    } finally {
      setSaving(false);
    }
  }

  async function changePassword(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!isStrongPassword(newPassword)) {
      toast.error(ui(
        'Le nouveau mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.',
        'The new password needs 12+ characters, uppercase, lowercase, a number, and a special character.',
      ));
      return;
    }
    if (newPassword !== passwordConfirmation) {
      toast.error(ui('Les mots de passe ne correspondent pas.', 'Passwords do not match.'));
      return;
    }
    if (currentPassword === newPassword) {
      toast.error(ui('Le nouveau mot de passe doit être différent.', 'The new password must be different.'));
      return;
    }

    setChangingPassword(true);
    try {
      await authApi.changePassword({
        currentPassword,
        password: newPassword,
        passwordConfirmation,
      });
      clearSession();
      toast.success(ui(
        'Mot de passe modifié. Reconnectez-vous avec votre nouveau mot de passe.',
        'Password changed. Sign in again with your new password.',
      ));
      window.location.replace(loginRouteFor(user!.role.name));
    } catch (error: any) {
      const validationErrors = error?.response?.data?.errors;
      const message = validationErrors?.current_password?.[0]
        ?? validationErrors?.password?.[0]
        ?? error?.response?.data?.message
        ?? ui('Impossible de modifier le mot de passe.', 'Unable to change the password.');
      toast.error(message);
    } finally {
      setChangingPassword(false);
    }
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-semibold text-ink">
          <UserRound className="h-6 w-6" /> {ui('Mon profil', 'My profile')}
        </h1>
        <p className="mt-1 text-sm text-secondary-500">
          {ui('Gérez vos informations personnelles et la sécurité de votre compte.', 'Manage your personal information and account security.')}
        </p>
      </div>

      <Card>
        <CardHeader>
          <div className="flex items-center gap-4">
            <Avatar name={user.full_name} src={user.avatar} size="lg" />
            <div>
              <CardTitle>{user.full_name}</CardTitle>
              <CardDescription>{user.role.display_name} · {user.email}</CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <form onSubmit={save} className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Input label="First name" required value={firstName} onChange={(e) => setFirstName(e.target.value)} />
            <Input label="Last name" required value={lastName} onChange={(e) => setLastName(e.target.value)} />
            <Input
              type="email"
              label={ui('Adresse e-mail de connexion', 'Login email')}
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              autoComplete="email"
              hint={ui('Les futurs codes OTP seront envoyés à cette adresse.', 'Future OTP codes will be sent to this address.')}
              required
            />
            <Input label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+237..." />
            {email.trim().toLowerCase() !== user.email.toLowerCase() && (
              <div className="md:col-span-2">
                <Input
                  type="password"
                  label={ui('Mot de passe actuel pour confirmer', 'Current password to confirm')}
                  value={emailCurrentPassword}
                  onChange={(event) => setEmailCurrentPassword(event.target.value)}
                  autoComplete="current-password"
                  hint={ui('Obligatoire uniquement pour modifier l’adresse e-mail.', 'Required only when changing the email address.')}
                  required
                />
              </div>
            )}
            <div className="flex justify-end md:col-span-2">
              <Button type="submit" isLoading={saving}>Save changes</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShieldCheck className="h-5 w-5 text-primary-600" />
            {ui('Changer le mot de passe', 'Change password')}
          </CardTitle>
          <CardDescription>
            {ui(
              'Après la modification, toutes les sessions seront fermées et vous devrez vous reconnecter.',
              'After changing it, every active session will be signed out and you must sign in again.',
            )}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={changePassword} className="space-y-4">
            <Input
              type={showPasswords ? 'text' : 'password'}
              label={ui('Mot de passe actuel', 'Current password')}
              autoComplete="current-password"
              value={currentPassword}
              onChange={(event) => setCurrentPassword(event.target.value)}
              required
            />
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Input
                type={showPasswords ? 'text' : 'password'}
                label={ui('Nouveau mot de passe', 'New password')}
                autoComplete="new-password"
                value={newPassword}
                onChange={(event) => setNewPassword(event.target.value)}
                hint={ui('12+ caractères, majuscule, minuscule, chiffre et symbole.', '12+ characters with upper, lower, number, and symbol.')}
                required
              />
              <Input
                type={showPasswords ? 'text' : 'password'}
                label={ui('Confirmer le mot de passe', 'Confirm new password')}
                autoComplete="new-password"
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                required
              />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <button
                type="button"
                onClick={() => setShowPasswords((visible) => !visible)}
                className="inline-flex items-center gap-2 text-sm text-secondary-600 hover:text-primary-600"
              >
                {showPasswords ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                {showPasswords ? ui('Masquer les mots de passe', 'Hide passwords') : ui('Afficher les mots de passe', 'Show passwords')}
              </button>
              <Button
                type="submit"
                isLoading={changingPassword}
                disabled={!currentPassword || !newPassword || !passwordConfirmation}
              >
                {ui('Changer le mot de passe', 'Change password')}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
