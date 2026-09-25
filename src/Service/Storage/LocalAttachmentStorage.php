<?php

declare(strict_types=1);

namespace App\Service\Storage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Storage allegati su volume Docker locale (`/var/storage/uploads`).
 * Path layout: `<root>/<namespace>/<YYYY>/<MM>/<random>.<ext>`.
 *
 * Whitelist MIME e size_max sono enforced a livello controller, qui ci limitiamo
 * a scrivere il file dove sicuro (mai con il filename originale).
 */
final class LocalAttachmentStorage implements AttachmentStorageInterface
{
    private readonly string $root;
    private readonly Filesystem $fs;

    public function __construct(
        #[Autowire(param: 'app.attachments_root')] string $root,
    ) {
        $this->root = rtrim($root, '/');
        $this->fs = new Filesystem();
    }

    public function store(UploadedFile $file, string $namespace): string
    {
        $now = new \DateTimeImmutable();
        $relativeDir = sprintf('%s/%s/%s', $namespace, $now->format('Y'), $now->format('m'));
        $absDir = $this->root.'/'.$relativeDir;

        // 0755 dir + 0644 file: leggibili dal processo app (BinaryFileResponse
        // li serve direttamente dopo il check di autorizzazione lato Symfony).
        if (!$this->fs->exists($absDir)) {
            $this->fs->mkdir($absDir, 0755);
        }

        $extension = $this->safeExtension($file);
        $filename = bin2hex(random_bytes(16)).($extension ? '.'.$extension : '');
        $file->move($absDir, $filename);
        $this->fs->chmod($absDir.'/'.$filename, 0644);

        return $relativeDir.'/'.$filename;
    }

    public function delete(string $storedPath): void
    {
        $abs = $this->absolutePath($storedPath);
        if ($this->fs->exists($abs)) {
            $this->fs->remove($abs);
        }
    }

    public function absolutePath(string $storedPath): string
    {
        return $this->root.'/'.ltrim($storedPath, '/');
    }

    public function exists(string $storedPath): bool
    {
        return $this->fs->exists($this->absolutePath($storedPath));
    }

    private function safeExtension(UploadedFile $file): ?string
    {
        $ext = strtolower((string) $file->guessExtension());
        // Whitelist coerente con quella del controller
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true) ? $ext : null;
    }
}
