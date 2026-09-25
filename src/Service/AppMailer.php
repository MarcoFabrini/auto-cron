<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Punto unico di invio email dell'app. SMTP si configura SOLO da env
 * (MAILER_DSN, vedi config/packages/mailer.yaml) — niente UI, niente DB: un
 * endpoint scrivibile per un segreto così sensibile (può dirottare tutte le
 * email, inclusi i reset password, a chiunque lo scriva) è superficie
 * d'attacco che un'istanza self-host non ha motivo di esporre. Finché
 * MAILER_DSN non è impostato, il DSN di default (`null://null`) rende
 * l'invio un vero no-op — osservabile/loggabile come un invio normale
 * invece di un semplice `return` silenzioso sparso nei chiamanti.
 */
final class AppMailer
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(Email $email): void
    {
        $this->mailer->send($email);
    }
}
