<?php

namespace App\SharedKernel\Application\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AvatarUploadService
{
    public const TYPE_EMPLOYEES = 'employees';
    public const TYPE_PRESTATAIRES = 'prestataires';
    public const TYPE_UTILISATEURS = 'utilisateurs';

    public const PUBLIC_PATH_PREFIX = '/uploads/avatars/';
    private const RELATIVE_BASE = 'public/uploads/avatars';
    private const MAX_SIZE = '2M';

    /** @var list<string> */
    private const ALLOWED_TYPES = [
        self::TYPE_EMPLOYEES,
        self::TYPE_PRESTATAIRES,
        self::TYPE_UTILISATEURS,
    ];

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** @var array<string, string> */
    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly string $projectDir,
    ) {
    }

    public function upload(string $type, Uuid $id, UploadedFile $file): string
    {
        $this->assertValidType($type);
        $this->assertValidImage($file);

        $mime = (string) $file->getMimeType();
        $extension = self::MIME_TO_EXTENSION[$mime] ?? null;
        if (null === $extension) {
            throw new \InvalidArgumentException('Seules les images JPEG, PNG, WebP et GIF sont acceptées.');
        }

        $directory = $this->directoryFor($type);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage des photos.');
        }

        $uuid = $id->toRfc4122();
        $this->removeExistingFiles($directory, $uuid);

        $filename = $uuid.'.'.$extension;
        $file->move($directory, $filename);

        return self::PUBLIC_PATH_PREFIX.$type.'/'.$filename.'?v='.time();
    }

    public function clear(string $type, Uuid $id): void
    {
        $this->assertValidType($type);
        $directory = $this->directoryFor($type);
        if (is_dir($directory)) {
            $this->removeExistingFiles($directory, $id->toRfc4122());
        }
    }

    private function assertValidType(string $type): void
    {
        if (!\in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Type de photo invalide.');
        }
    }

    private function assertValidImage(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide ou upload incomplet.');
        }

        $violations = $this->validator->validate($file, [
            new Assert\NotBlank(message: 'Aucun fichier reçu.'),
            new Assert\Image(
                maxSize: self::MAX_SIZE,
                mimeTypes: self::ALLOWED_MIME_TYPES,
                mimeTypesMessage: 'Seules les images JPEG, PNG, WebP et GIF sont acceptées.',
                maxSizeMessage: 'La photo ne doit pas dépasser {{ limit }} {{ suffix }}.',
            ),
        ]);

        if (\count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations->get(0)->getMessage());
        }

        $mime = (string) $file->getMimeType();
        if (!isset(self::MIME_TO_EXTENSION[$mime])) {
            throw new \InvalidArgumentException('Seules les images JPEG, PNG, WebP et GIF sont acceptées.');
        }
    }

    private function directoryFor(string $type): string
    {
        return $this->projectDir.\DIRECTORY_SEPARATOR.str_replace(
            '/',
            \DIRECTORY_SEPARATOR,
            self::RELATIVE_BASE.'/'.$type,
        );
    }

    private function removeExistingFiles(string $directory, string $uuid): void
    {
        foreach (glob($directory.\DIRECTORY_SEPARATOR.$uuid.'.*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
