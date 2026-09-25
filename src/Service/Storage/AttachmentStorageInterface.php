<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Astrae la persistenza fisica degli allegati.
 *
 * Implementazioni:
 * - {@see LocalAttachmentStorage} → volume Docker locale (self-host & dev)
 * - (futuro) `FlysystemAttachmentStorage` con adapter S3 → fase SaaS
 *
 * L'app fa SEMPRE riferimento all'interfaccia. Lo swap avviene via alias
 * in services.yaml senza modificare il codice di chiamata.
 */
interface AttachmentStorageInterface
{
    /**
     * Persiste il file e ritorna il `storedPath` da salvare nell'entity.
     * Lo stored path NON contiene il filename originale (per sicurezza).
     */
    public function store(UploadedFile $file, string $namespace): string;

    public function delete(string $storedPath): void;

    /**
     * Ritorna il path assoluto su filesystem, usato per servire il file in
     * streaming (BinaryFileResponse) dopo il check di autorizzazione.
     * Per backend cloud, ritorna invece un URL pre-signed o null e si scarica via stream.
     */
    public function absolutePath(string $storedPath): string;

    public function exists(string $storedPath): bool;
}
