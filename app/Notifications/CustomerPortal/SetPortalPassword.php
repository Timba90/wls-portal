<?php

namespace App\Notifications\CustomerPortal;

use App\Models\PortalUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Link zum Setzen des Portal-Passworts.
 *
 * Dieselbe Mechanik fuer Einladung und Ruecksetzung — nur der Text
 * unterscheidet sich. Bewusst ohne Warteschlange: der Link soll sofort
 * ankommen, und das Token laeuft nach einer Stunde ab.
 */
class SetPortalPassword extends Notification
{
    public function __construct(
        public string $token,
        public bool $invitation = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(PortalUser $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(PortalUser $notifiable): MailMessage
    {
        $url = route('customer-portal.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        $minuten = (int) config('auth.passwords.portal_users.expire');
        $ablauf = $minuten >= 120 ? intdiv($minuten, 60).' Stunden' : $minuten.' Minuten';

        if ($this->invitation) {
            return (new MailMessage)
                ->subject('Ihr Zugang zum Kundenportal von '.config('portal.brand.name'))
                ->greeting('Hallo '.$notifiable->name.',')
                ->line('für Sie wurde ein Zugang zum Kundenportal eingerichtet. Dort finden Sie Ihre Rechnungen, Ihr SEPA-Mandat, Ihre Tickets und den Stand Ihrer Projekte.')
                ->action('Passwort festlegen', $url)
                ->line('Der Link ist '.$ablauf.' gültig. Danach können Sie über „Passwort vergessen?" einen neuen anfordern.');
        }

        return (new MailMessage)
            ->subject('Passwort für das Kundenportal zurücksetzen')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Sie haben angefordert, Ihr Passwort für das Kundenportal zurückzusetzen.')
            ->action('Neues Passwort festlegen', $url)
            ->line('Der Link ist '.$ablauf.' gültig. Haben Sie nichts angefordert, können Sie diese E-Mail ignorieren.');
    }
}
