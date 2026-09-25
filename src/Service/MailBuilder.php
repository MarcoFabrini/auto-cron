<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\Reminder;
use App\Entity\User;
use App\Enum\ReminderUrgency;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Costruisce le email transazionali dell'app come {@see Email} branded (HTML +
 * testo) a partire da un template Twig generico, con copy localizzata (it/en)
 * in base alla lingua dell'utente. Il chiamante imposta solo `->to()` e passa
 * l'Email ad {@see AppMailer} per l'invio.
 *
 * Niente componente translation: la copy vive qui, per-locale, così i template
 * restano pura impaginazione e l'invio non dipende da listener Twig (l'SMTP
 * custom di AppMailer crea un Mailer senza dispatcher → renderizziamo a stringa).
 */
final class MailBuilder
{
    private const SUPPORTED = ['it', 'en'];

    public function __construct(
        private readonly Environment $twig,
        #[Autowire(param: 'app.frontend_url')] private readonly string $frontendUrl,
        #[Autowire(param: 'app.mailer_from')] private readonly string $from,
    ) {
    }

    public function reminder(
        User $user,
        Reminder $reminder,
        ReminderUrgency $urgency = ReminderUrgency::SOON,
        ?int $currentKm = null,
    ): Email {
        $loc = $this->locale($user);
        $vehicle = $reminder->getVehicle()->getName();
        $desc = $reminder->getDescription();
        $first = $user->getFirstName();
        $overdue = $urgency === ReminderUrgency::OVERDUE;
        $deadline = $this->reminderDeadline($reminder, $currentKm, $loc);

        $copy = $loc === 'en'
            ? [
                'subject' => 'AutoCron — Reminder: '.$desc,
                'heading' => $overdue ? 'Reminder overdue' : 'Upcoming reminder',
                'greeting' => sprintf('Hi %s,', $first),
                'paragraphs' => [
                    sprintf($overdue ? 'A reminder for your vehicle %s is overdue:' : 'You have an upcoming reminder for your vehicle %s:', $vehicle),
                    $deadline !== '' ? sprintf('%s — %s', $desc, $deadline) : $desc,
                ],
                'button' => ['label' => 'Open AutoCron', 'url' => $this->link('')],
                'footer' => "You're receiving this because you have access to this vehicle in AutoCron.",
            ]
            : [
                'subject' => 'AutoCron — Scadenza: '.$desc,
                'heading' => $overdue ? 'Scadenza superata' : 'Scadenza in arrivo',
                'greeting' => sprintf('Ciao %s,', $first),
                'paragraphs' => [
                    sprintf($overdue ? 'È scaduta una scadenza per il tuo veicolo %s:' : 'C\'è una scadenza in arrivo per il tuo veicolo %s:', $vehicle),
                    $deadline !== '' ? sprintf('%s — %s', $desc, $deadline) : $desc,
                ],
                'button' => ['label' => 'Apri AutoCron', 'url' => $this->link('')],
                'footer' => 'Ricevi questa email perché hai accesso a questo veicolo in AutoCron.',
            ];

        return $this->build($loc, $copy);
    }

    /** "scadenza 15/10/2026 · a 100.000 km (attuali: 99.500 km)" — solo le scadenze presenti. */
    private function reminderDeadline(Reminder $reminder, ?int $currentKm, string $locale): string
    {
        $en = $locale === 'en';
        $parts = [];

        if ($reminder->getDueDate() !== null) {
            $parts[] = ($en ? 'due ' : 'scadenza ').$reminder->getDueDate()->format($en ? 'Y-m-d' : 'd/m/Y');
        }
        if ($reminder->getDueKm() !== null) {
            $fmt = static fn (int $km): string => number_format($km, 0, $en ? '.' : ',', $en ? ',' : '.').' km';
            $parts[] = sprintf(
                $en ? 'at %s%s' : 'a %s%s',
                $fmt($reminder->getDueKm()),
                $currentKm !== null ? sprintf($en ? ' (now: %s)' : ' (attuali: %s)', $fmt($currentKm)) : '',
            );
        }

        return implode(' · ', $parts);
    }

    public function resetPassword(User $user, string $rawToken): Email
    {
        $loc = $this->locale($user);
        $url = $this->link('/reset-password', $rawToken);
        $first = $user->getFirstName();

        $copy = $loc === 'en'
            ? [
                'subject' => 'AutoCron — Reset your password',
                'heading' => 'Reset your password',
                'greeting' => sprintf('Hi %s,', $first),
                'paragraphs' => ['You asked to reset your AutoCron password. This link is valid for 1 hour.'],
                'button' => ['label' => 'Reset password', 'url' => $url],
                'outro' => "If you didn't request this, ignore this email — your password stays unchanged.",
                'footer' => 'AutoCron — self-hosted vehicle management.',
            ]
            : [
                'subject' => 'AutoCron — Reimposta la tua password',
                'heading' => 'Reimposta la password',
                'greeting' => sprintf('Ciao %s,', $first),
                'paragraphs' => ['Hai richiesto di reimpostare la password del tuo account AutoCron. Il link è valido 1 ora.'],
                'button' => ['label' => 'Reimposta password', 'url' => $url],
                'outro' => 'Se non hai richiesto tu il reset, ignora questa email: la password resta invariata.',
                'footer' => 'AutoCron — gestione veicoli self-host.',
            ];

        return $this->build($loc, $copy);
    }

    public function verifyEmail(User $user, string $rawToken): Email
    {
        $loc = $this->locale($user);
        $url = $this->link('/verify-email', $rawToken);
        $first = $user->getFirstName();

        $copy = $loc === 'en'
            ? [
                'subject' => 'AutoCron — Confirm your email',
                'heading' => 'Confirm your email',
                'greeting' => sprintf('Hi %s,', $first),
                'paragraphs' => ['Welcome to AutoCron! Confirm your email address to finish setting up your account. This link is valid for 24 hours.'],
                'button' => ['label' => 'Confirm email', 'url' => $url],
                'outro' => "If you didn't create an AutoCron account, ignore this email.",
                'footer' => 'AutoCron — self-hosted vehicle management.',
            ]
            : [
                'subject' => 'AutoCron — Conferma il tuo indirizzo email',
                'heading' => 'Conferma la tua email',
                'greeting' => sprintf('Ciao %s,', $first),
                'paragraphs' => ['Benvenuto in AutoCron! Conferma il tuo indirizzo email per completare la registrazione. Il link è valido 24 ore.'],
                'button' => ['label' => 'Conferma email', 'url' => $url],
                'outro' => 'Se non ti sei registrato tu, ignora questa email.',
                'footer' => 'AutoCron — gestione veicoli self-host.',
            ];

        return $this->build($loc, $copy);
    }

    /**
     * Invito a un'organizzazione: l'invitato può non avere ancora un account,
     * quindi accettiamo solo l'email (niente nome) + locale opzionale (default it).
     */
    public function invitation(string $email, Organization $org, ?User $inviter, string $rawToken, string $locale = 'it'): Email
    {
        $loc = in_array($locale, self::SUPPORTED, true) ? $locale : 'it';
        $url = $this->link('/accept-invite', $rawToken);
        $orgName = $org->getName();
        $inviterName = $inviter !== null
            ? trim($inviter->getFirstName().' '.$inviter->getLastName())
            : null;

        $copy = $loc === 'en'
            ? [
                'subject' => 'AutoCron — Invitation to '.$orgName,
                'heading' => "You've been invited",
                'greeting' => null,
                'paragraphs' => [
                    $inviterName !== null
                        ? sprintf('%s invited you to join the organization "%s" on AutoCron.', $inviterName, $orgName)
                        : sprintf('You have been invited to join the organization "%s" on AutoCron.', $orgName),
                    'Accept the invitation to start sharing vehicles — you can create your account on the spot if you don\'t have one. This link is valid for 7 days.',
                ],
                'button' => ['label' => 'Accept invitation', 'url' => $url],
                'outro' => "If you weren't expecting this invitation, ignore this email.",
                'footer' => 'AutoCron — self-hosted vehicle management.',
            ]
            : [
                'subject' => 'AutoCron — Invito a '.$orgName,
                'heading' => 'Sei stato invitato',
                'greeting' => null,
                'paragraphs' => [
                    $inviterName !== null
                        ? sprintf('%s ti ha invitato a unirti all\'organizzazione "%s" su AutoCron.', $inviterName, $orgName)
                        : sprintf('Sei stato invitato a unirti all\'organizzazione "%s" su AutoCron.', $orgName),
                    'Accetta l\'invito per condividere i veicoli — se non hai un account puoi crearlo al momento. Il link è valido 7 giorni.',
                ],
                'button' => ['label' => 'Accetta invito', 'url' => $url],
                'outro' => 'Se non ti aspettavi questo invito, ignora questa email.',
                'footer' => 'AutoCron — gestione veicoli self-host.',
            ];

        return $this->build($loc, $copy);
    }

    private function locale(User $user): string
    {
        return in_array($user->getLocale(), self::SUPPORTED, true) ? $user->getLocale() : 'it';
    }

    private function link(string $path, ?string $token = null): string
    {
        $url = rtrim($this->frontendUrl, '/').$path;
        return $token !== null ? $url.'?token='.$token : $url;
    }

    /**
     * @param array{subject:string, heading:string, greeting?:string|null, paragraphs?:list<string>, button?:array{label:string,url:string}|null, outro?:string|null, footer:string} $copy
     */
    private function build(string $locale, array $copy): Email
    {
        $context = [
            'locale' => $locale,
            'subject' => $copy['subject'],
            'preheader' => $copy['paragraphs'][0] ?? $copy['heading'],
            'heading' => $copy['heading'],
            'greeting' => $copy['greeting'] ?? null,
            'paragraphs' => $copy['paragraphs'] ?? [],
            'button' => $copy['button'] ?? null,
            'outro' => $copy['outro'] ?? null,
            'footer' => $copy['footer'],
        ];

        return (new Email())
            ->from($this->from)
            ->subject($copy['subject'])
            ->html($this->twig->render('emails/transactional.html.twig', $context))
            ->text($this->twig->render('emails/transactional.txt.twig', $context));
    }
}
