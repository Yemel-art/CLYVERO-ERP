<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TenantPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $plainToken) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $notifiable->loadMissing('school');
        $locale = $notifiable->school?->default_locale ?? config('app.locale', 'fr');
        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->plainToken,
            'email' => $notifiable->email,
            'school' => $notifiable->school?->school_code,
        ]);
        $minutes = (int) config('auth.passwords.users.expire', 30);

        if ($locale === 'fr') {
            return (new MailMessage())
                ->subject('Reinitialisation de votre mot de passe')
                ->greeting('Bonjour '.$notifiable->first_name.',')
                ->line('Une demande de reinitialisation du mot de passe a ete effectuee pour votre compte.')
                ->action('Reinitialiser le mot de passe', $url)
                ->line("Ce lien unique expire dans {$minutes} minutes.")
                ->line("Si vous n'avez pas fait cette demande, aucune action n'est necessaire.");
        }

        return (new MailMessage())
            ->subject('Reset your password')
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line('A password reset was requested for your account.')
            ->action('Reset password', $url)
            ->line("This one-time link expires in {$minutes} minutes.")
            ->line('If you did not request this reset, no action is required.');
    }
}
