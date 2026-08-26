'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Send, Check, Bell, Info, CheckCircle, AlertTriangle, XCircle } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { useNotifications, useMarkAllNotificationsRead, useMarkNotificationRead, useBroadcastNotification } from '@/hooks/notifications';
import { useAuthStore } from '@/store/auth';
import type { NotificationType } from '@/lib/api/notifications';
import { useTranslation } from '@/hooks/useTranslation';
import { dashboardRouteFor } from '@/types/user';

const typeIcon = {
  info: Info,
  success: CheckCircle,
  warning: AlertTriangle,
  danger: XCircle,
};

const typeColor: Record<NotificationType, string> = {
  info: 'text-info bg-info-light',
  success: 'text-success bg-success-light',
  warning: 'text-warning bg-warning-light',
  danger: 'text-danger bg-danger-light',
};

export default function NotificationsPage() {
  const user = useAuthStore((s) => s.user);
  const canBroadcast = user?.role?.name === 'administrator';
  const { language } = useTranslation();
  const ui = (french: string, english: string) => language === 'fr' ? french : english;
  const dashboardHref = user ? dashboardRouteFor(user.role.name) : '/login';

  const { data, isLoading } = useNotifications({ limit: 50 });
  const markAll = useMarkAllNotificationsRead();
  const markOne = useMarkNotificationRead();
  const broadcast = useBroadcastNotification();

  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [type, setType] = useState<NotificationType>('info');
  const [role, setRole] = useState('');

  const send = async () => {
    if (!title) return;
    try {
      const r = await broadcast.mutateAsync({ title, body: body || undefined, type, role: role || undefined });
      toast.success(language === 'fr' ? `Notification envoyée à ${r.recipients} utilisateurs.` : `Sent to ${r.recipients} users.`);
      setTitle(''); setBody('');
    } catch {
      toast.error(ui('Impossible d’envoyer la notification.', 'Could not send notification.'));
    }
  };

  return (
    <div>
      <PageHeader
        breadcrumb={[{ label: ui('Accueil', 'Home'), href: dashboardHref }, { label: 'Notifications' }]}
        title="Notifications"
        description={data ? (language === 'fr' ? `${data.unread_count} non lue(s)` : `${data.unread_count} unread`) : ui('Chargement…', 'Loading…')}
        actions={data && data.unread_count > 0 && (
          <Button variant="outline" leftIcon={<Check className="h-4 w-4" />} onClick={async () => {
            const r = await markAll.mutateAsync();
            toast.success(language === 'fr' ? `${r.marked} notification(s) marquée(s) comme lue(s).` : `Marked ${r.marked} as read.`);
          }}>{ui('Tout marquer comme lu', 'Mark all read')}</Button>
        )}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className={canBroadcast ? 'lg:col-span-2' : 'lg:col-span-3'}>
          {isLoading ? <Skeleton className="h-64" /> : !data?.data || data.data.length === 0 ? (
            <Card><CardContent className="py-12 text-center">
              <Bell className="mx-auto mb-2 h-8 w-8 text-secondary-300" />
              <p className="text-sm text-secondary-500">{ui('Aucune notification pour le moment.', 'No notifications yet.')}</p>
            </CardContent></Card>
          ) : (
            <Card>
              <CardContent className="py-2">
                <ul className="divide-y divide-secondary-100">
                  {data.data.map((n) => {
                    const Icon = typeIcon[n.type];
                    const unread = !n.read_at;
                    return (
                      <li key={n.id} className={`py-3 ${unread ? 'bg-primary-50/40' : ''}`}>
                        <div className="flex items-start gap-3">
                          <div className={`rounded-button p-1.5 ${typeColor[n.type]}`}><Icon className="h-4 w-4" /></div>
                          <div className="flex-1">
                            <div className="flex items-center justify-between">
                              <p className="font-medium text-ink">{n.title}</p>
                              <span className="text-xs text-secondary-500">{new Date(n.created_at).toLocaleString()}</span>
                            </div>
                            {n.body && <p className="mt-0.5 text-sm text-secondary-600">{n.body}</p>}
                            {unread && (
                              <button onClick={() => markOne.mutateAsync(n.id)}
                                className="mt-1 text-xs text-primary-600 hover:underline">{ui('Marquer comme lu', 'Mark as read')}</button>
                            )}
                          </div>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              </CardContent>
            </Card>
          )}
        </div>

        {canBroadcast && (
          <Card>
            <CardHeader>
              <div className="flex items-center gap-2"><Send className="h-4 w-4 text-primary-600" /><CardTitle className="text-base">{ui('Diffusion', 'Broadcast')}</CardTitle></div>
              <CardDescription>{ui('Envoyer une notification à tous les utilisateurs ou à un rôle précis.', 'Send a notification to all users or a specific role.')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <Input label={ui('Titre', 'Title')} value={title} onChange={(e) => setTitle(e.target.value)} placeholder={ui('Rappel : le trimestre se termine vendredi', 'Reminder: Term ends Friday')} />
              <div>
                <label className="mb-1.5 block text-sm font-medium text-secondary-700">{ui('Message (facultatif)', 'Body (optional)')}</label>
                <textarea rows={3} value={body} onChange={(e) => setBody(e.target.value)}
                  className="w-full rounded-input border border-secondary-300 bg-surface p-3 text-sm" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-secondary-700">{ui('Type', 'Type')}</label>
                  <select value={type} onChange={(e) => setType(e.target.value as NotificationType)}
                    className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                    <option value="info">Info</option>
                    <option value="success">{ui('Succès', 'Success')}</option>
                    <option value="warning">{ui('Avertissement', 'Warning')}</option>
                    <option value="danger">Danger</option>
                  </select>
                </div>
                <div className="flex flex-col gap-1.5">
                  <label className="text-sm font-medium text-secondary-700">{ui('Destinataires', 'Audience')}</label>
                  <select value={role} onChange={(e) => setRole(e.target.value)}
                    className="h-10 rounded-input border border-secondary-300 bg-surface px-3 text-sm">
                    <option value="">{ui('Tout le monde', 'Everyone')}</option>
                    <option value="administrator">{ui('Administrateurs', 'Administrators')}</option>
                    <option value="secretary">{ui('Secrétaires', 'Secretaries')}</option>
                    <option value="teacher">{ui('Enseignants', 'Teachers')}</option>
                    <option value="parent">Parents</option>
                  </select>
                </div>
              </div>
              <Button onClick={send} isLoading={broadcast.isPending} disabled={!title}
                leftIcon={<Send className="h-4 w-4" />}>{ui('Envoyer', 'Send')}</Button>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
