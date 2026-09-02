<?php

namespace App\Document\Application\Service;

use App\Document\Domain\Entity\Document;
use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class DocumentUploadService
{
    public const PUBLIC_PATH_PREFIX = '/uploads/documents/';
    private const RELATIVE_DIR = 'public/uploads/documents';

    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly string $projectDir,
    ) {
    }

    public function upload(
        UploadedFile $file,
        string $title,
        DocumentOwnerType $ownerType,
        Uuid $ownerId,
        ?string $description = null,
    ): Document {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide ou upload incomplet.');
        }

        $title = FieldValidator::requireNonEmpty($title, 'Titre');
        $directory = $this->projectDir.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, self::RELATIVE_DIR);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier de stockage des documents.');
        }

        $originalName = $file->getClientOriginalName();
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName) ?? 'document';
        $filename = Uuid::v7()->toRfc4122().'_'.$safeName;
        $file->move($directory, $filename);

        $document = new Document(
            title: $title,
            fileName: $originalName,
            filePath: self::PUBLIC_PATH_PREFIX.$filename,
            ownerType: $ownerType,
            ownerId: $ownerId,
            description: $description,
        );
        $this->documentRepository->save($document);

        return $document;
    }

    public function deleteFile(string $filePath): void
    {
        if (!str_starts_with($filePath, self::PUBLIC_PATH_PREFIX)) {
            return;
        }

        $relative = ltrim(str_replace(self::PUBLIC_PATH_PREFIX, 'uploads/documents/', $filePath), '/');
        $absolute = $this->projectDir.\DIRECTORY_SEPARATOR.'public'.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, $relative);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
