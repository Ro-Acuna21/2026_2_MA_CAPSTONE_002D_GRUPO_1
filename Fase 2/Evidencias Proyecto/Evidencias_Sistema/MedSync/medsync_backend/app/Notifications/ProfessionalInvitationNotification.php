<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly ?string $medicalCenterName = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            (string) config('app.frontend_url', 'http://localhost:5173'),
            '/'
        );

        $activationUrl = $frontendUrl
            .'/activar-cuenta?'
            .http_build_query([
                'token' => $this->token,
                'email' => $notifiable->email,
            ]);

        $centerName = $this->medicalCenterName ?? 'el centro médico';

        return (new MailMessage)
            ->subject('Activa tu acceso a MedSync')
            ->greeting('Hola '.$notifiable->name)
            ->line(
                $centerName
                .' ha habilitado tu acceso como profesional en MedSync.'
            )
            ->line(
                'Para comenzar a utilizar tu cuenta, crea tu contraseña mediante el siguiente enlace.'
            )
            ->action('Crear mi contraseña', $activationUrl)
            ->line('Este enlace estará disponible durante 60 minutos.')
            ->line(
                'Si no reconoces esta invitación, puedes ignorar este correo.'
            );
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}