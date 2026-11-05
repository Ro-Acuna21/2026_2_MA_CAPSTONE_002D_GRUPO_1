<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CenterAccessInvitationNotification extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly string $centerName,
        private readonly string $roleName,
    ) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/')
            .'/activar-cuenta?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email]);
        return (new MailMessage)
            ->subject('Activa tu acceso a MedSync')
            ->greeting('Hola '.$notifiable->name)
            ->line($this->centerName.' te invitó a MedSync como '.$this->roleName.'.')
            ->line('Crea tu contraseña para ingresar al portal del centro.')
            ->action('Crear mi contraseña', $url)
            ->line('El enlace estará disponible durante 60 minutos.');
    }
}
