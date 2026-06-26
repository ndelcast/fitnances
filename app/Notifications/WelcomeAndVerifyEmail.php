<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email de bienvenue + vérification du correo en un seul envoi.
 *
 * Hérite de VerifyEmail pour réutiliser la logique de signature
 * d'URL et la configuration du temps d'expiration via
 * VerifyEmail::createUrlUsing() / ->expireMinutes(...).
 */
class WelcomeAndVerifyEmail extends BaseVerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verifyUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('¡Bienvenido a Fitnances!')
            ->greeting('¡Hola '.$notifiable->name.'!')
            ->line('Bienvenido a **Fitnances** — la app que te dice cada mes cuánto puedes pagarte realmente, una vez apartados IVA, IRPF y cuota.')
            ->line('Para empezar, confirma tu correo electrónico haciendo clic en el botón siguiente.')
            ->action('Verificar mi correo', $verifyUrl)
            ->line('Una vez verificado, el asistente anual te guiará en menos de dos minutos.')
            ->salutation('Hasta pronto, el equipo Fitnances')
            ->line('Si no has creado esta cuenta, ignora este mensaje.');
    }
}
