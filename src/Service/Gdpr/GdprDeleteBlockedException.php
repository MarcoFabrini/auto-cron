<?php

declare(strict_types=1);

namespace App\Service\Gdpr;

/**
 * Lanciata quando GDPR delete è bloccato da precondizione
 * (es. unico owner di un'organization condivisa) — vedi GdprDeleteService.
 */
final class GdprDeleteBlockedException extends \RuntimeException
{
}
