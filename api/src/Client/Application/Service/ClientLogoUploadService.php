<?php

namespace App\Client\Application\Service;

use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ClientLogoUploadService
{
    public const PUBLIC_PATH_PREFIX = '/uploads/client-logos/';
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MIME_TO_EXTENSION = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly ValidatorInterface $validator,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function upload(Client $client, UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide ou upload incomplet.');
        }
        $violations = $this->validator->validate($file, new Assert\Image(
            maxSize: self::MAX_BYTES,
            mimeTypes: array_keys(self::MIME_TO_EXTENSION),
            maxWidth: 4096,
            maxHeight: 4096,
            detectCorrupted: true,
            mimeTypesMessage: 'Seules les images JPEG, PNG, WebP et GIF sont acceptées.',
            maxSizeMessage: 'Le logo ne doit pas dépasser 2 Mo.',
        ));
        if (count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations->get(0)->getMessage());
        }
        $extension = self::MIME_TO_EXTENSION[$file->getMimeType() ?? ''] ?? null;
        if ($extension === null) {
            throw new \InvalidArgumentException('Seules les images JPEG, PNG, WebP et GIF sont acceptées.');
        }
        $directory = $this->directory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage des logos clients.');
        }

        // A unique name keeps the previous logo intact until persistence succeeds.
        $filename = $client->getId()->toRfc4122().'-'.bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($directory, $filename);
        $oldUrl = $client->getLogoUrl();
        $client->setLogoUrl(self::PUBLIC_PATH_PREFIX.$filename);
        try {
            $this->clients->save($client);
        } catch (\Throwable $e) {
            $client->setLogoUrl($oldUrl);
            @unlink($directory.DIRECTORY_SEPARATOR.$filename);
            throw $e;
        }
        $this->remove($oldUrl);
    }

    public function clear(Client $client): void
    {
        $oldUrl = $client->getLogoUrl();
        $client->setLogoUrl(null);
        try {
            $this->clients->save($client);
        } catch (\Throwable $e) {
            $client->setLogoUrl($oldUrl);
            throw $e;
        }
        $this->remove($oldUrl);
    }

    public function resolveForDocuments(?string $logoUrl): ?string
    {
        $path = $this->localPath($logoUrl);
        if ($path === null || filesize($path) > self::MAX_BYTES) {
            return null;
        }
        $mime = mime_content_type($path);
        $image = @getimagesize($path);
        if (!isset(self::MIME_TO_EXTENSION[$mime ?: '']) || $image === false || $image[0] > 4096 || $image[1] > 4096 || $image['mime'] !== $mime) {
            return null;
        }
        $content = file_get_contents($path);

        return $content === false ? null : 'data:'.$mime.';base64,'.base64_encode($content);
    }

    private function directory(): string
    {
        return $this->kernel->getProjectDir().'/public'.self::PUBLIC_PATH_PREFIX;
    }

    private function localPath(?string $url): ?string
    {
        if ($url === null || !preg_match('~^/uploads/client-logos/[0-9a-f-]{36}-[0-9a-f]{32}\.(?:jpg|png|webp|gif)$~D', $url)) {
            return null;
        }
        $base = realpath($this->directory());
        $path = realpath($this->kernel->getProjectDir().'/public'.$url);

        return $base !== false && $path !== false && str_starts_with($path, $base.DIRECTORY_SEPARATOR) && is_file($path) ? $path : null;
    }

    private function remove(?string $url): void
    {
        $path = $this->localPath($url);
        if ($path !== null) {
            @unlink($path);
        }
    }
}
