<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminLoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $notifiable->loadMissing('school');
        $requestedLocale = request()?->header('X-Locale');
        $locale = in_array($requestedLocale, ['fr', 'en'], true)
            ? $requestedLocale
            : ($notifiable->school?->default_locale ?? app()->getLocale());

        if ($locale === 'fr') {
            return (new MailMessage())
                ->subject('Code de vérification de connexion administrateur')
                ->greeting('Bonjour ' . $notifiable->first_name . ',')
                ->line('Une tentative de connexion a été effectuée sur votre compte administrateur.')
                ->line('Votre code de vérification à usage unique est :')
                ->line('**' . $this->code . '**')
                ->line("Ce code expire dans {$this->expiresInMinutes} minutes et ne peut être utilisé qu’une seule fois.")
                ->line('Si vous n’êtes pas à l’origine de cette connexion, changez immédiatement votre mot de passe.');
        }

        return (new MailMessage())
            ->subject('Administrator login verification code')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('A sign-in attempt was made for your administrator account.')
            ->line('Your one-time verification code is:')
            ->line('**' . $this->code . '**')
            ->line("This code expires in {$this->expiresInMinutes} minutes and can only be used once.")
            ->line('If you did not attempt to sign in, change your password immediately.');
    }
}
