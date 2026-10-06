<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
 *
 * Proprio perché il no-op è silenzioso (nessun errore, la mail non arriva e basta), con il trasporto
 * `null` ogni invio lascia un warning nel log: chi dimentica MAILER_DSN se ne accorge, e con lui i
 * promemoria "inviati" che nessuno riceve. Il comportamento dell'invio non cambia (test compresi).
 */
final class AppMailer
{
    private readonly bool $nullTransport;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'default:app_mailer_dsn_default:MAILER_DSN')]
        string $mailerDsn,
    ) {
        $this->nullTransport = parse_url($mailerDsn, PHP_URL_SCHEME) === 'null';
    }

    public function send(Email $email): void
    {
        if ($this->nullTransport) {
            // Solo l'oggetto: niente indirizzi nel log. Il DSN null scarta la mail senza errori.
            $this->logger->warning('MAILER_DSN non impostato (trasporto null): email non consegnata', [
                'subject' => $email->getSubject(),
            ]);
        }

        $this->mailer->send($email);
    }
}
