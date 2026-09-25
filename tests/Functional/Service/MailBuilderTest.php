<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\Reminder;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ReminderUrgency;
use App\Service\MailBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * MailBuilder: rende le email transazionali branded (HTML + testo) con copy
 * localizzata in base alla lingua dell'utente (closes #2.2).
 */
final class MailBuilderTest extends KernelTestCase
{
    private function mailBuilder(): MailBuilder
    {
        self::bootKernel();
        return static::getContainer()->get(MailBuilder::class);
    }

    private function user(string $locale): User
    {
        return (new User())->setEmail('u@test.it')->setFirstName('Mario')->setLastName('Rossi')->setLocale($locale);
    }

    public function testVerifyEmailItalianHasHtmlTextAndLink(): void
    {
        $email = $this->mailBuilder()->verifyEmail($this->user('it'), 'tok-abc');

        self::assertStringContainsString('Conferma', (string) $email->getSubject());

        $html = (string) $email->getHtmlBody();
        self::assertStringContainsString('Conferma la tua email', $html);
        self::assertStringContainsString('verify-email?token=tok-abc', $html);
        self::assertStringContainsString('<!DOCTYPE html>', $html, 'Corpo HTML branded');

        $text = (string) $email->getTextBody();
        self::assertStringContainsString('verify-email?token=tok-abc', $text);
    }

    public function testResetPasswordEnglishCopy(): void
    {
        $email = $this->mailBuilder()->resetPassword($this->user('en'), 'tok-xyz');

        self::assertStringContainsString('Reset your password', (string) $email->getSubject());
        self::assertStringContainsString('reset-password?token=tok-xyz', (string) $email->getHtmlBody());
    }

    public function testUnsupportedLocaleFallsBackToItalian(): void
    {
        $email = $this->mailBuilder()->verifyEmail($this->user('fr'), 'tok-1');

        self::assertStringContainsString('Conferma', (string) $email->getSubject());
    }

    private function kmReminder(): Reminder
    {
        return (new Reminder())
            ->setVehicle((new Vehicle())->setName('Panda'))
            ->setDescription('Cambio olio')
            ->setDueKm(100_000);
    }

    public function testReminderMentionsKmDeadlineAndCurrentOdometer(): void
    {
        $email = $this->mailBuilder()->reminder($this->user('it'), $this->kmReminder(), ReminderUrgency::SOON, 99_500);

        $text = (string) $email->getTextBody();
        self::assertStringContainsString('Cambio olio', $text);
        self::assertStringContainsString('a 100.000 km (attuali: 99.500 km)', $text);
        self::assertStringContainsString('Scadenza in arrivo', (string) $email->getHtmlBody());
    }

    public function testReminderOverdueEnglishCopy(): void
    {
        $email = $this->mailBuilder()->reminder($this->user('en'), $this->kmReminder(), ReminderUrgency::OVERDUE, 100_400);

        self::assertStringContainsString('Reminder overdue', (string) $email->getHtmlBody());
        self::assertStringContainsString('at 100,000 km (now: 100,400 km)', (string) $email->getTextBody());
    }

    public function testReminderWithBothDateAndKmListsBoth(): void
    {
        $r = $this->kmReminder()->setDueDate(new \DateTimeImmutable('2026-10-15'));

        $text = (string) $this->mailBuilder()->reminder($this->user('it'), $r, ReminderUrgency::SOON, null)->getTextBody();

        self::assertStringContainsString('scadenza 15/10/2026 · a 100.000 km', $text);
        self::assertStringNotContainsString('attuali', $text, 'senza km attuali noti non si inventa nulla');
    }
}
