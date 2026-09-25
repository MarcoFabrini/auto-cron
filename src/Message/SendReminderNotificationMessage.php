<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Messenger message: notifica una scadenza all'utente.
 *
 * Trasportato sul transport "async" (Doctrine, tabella messenger_messages). L'handler {@see App\MessageHandler\SendReminderNotificationHandler}
 * compone payload push + email e li invia.
 */
final readonly class SendReminderNotificationMessage
{
    public function __construct(
        public int $reminderId,
    ) {
    }
}
