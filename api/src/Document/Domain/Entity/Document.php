<?php

namespace App\Document\Domain\Entity;

use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Infrastructure\Persistence\Doctrine\DoctrineDocumentRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineDocumentRepository::class)]
#[ORM\Table(name: 'documents')]
class Document
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 255)]
    private string $fileName;

    #[ORM\Column(length: 500)]
    private string $filePath;

    #[ORM\Column(enumType: DocumentOwnerType::class)]
    private DocumentOwnerType $ownerType;

    #[ORM\Column(type: 'uuid')]
    private Uuid $ownerId;

    public function __construct(
        string $title,
        string $fileName,
        string $filePath,
        DocumentOwnerType $ownerType,
        Uuid $ownerId,
        ?string $description = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->title = $title;
        $this->fileName = $fileName;
        $this->filePath = $filePath;
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
        $this->description = $description;
    }

    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getFileName(): string { return $this->fileName; }
    public function getFilePath(): string { return $this->filePath; }
    public function getOwnerType(): DocumentOwnerType { return $this->ownerType; }
    public function getOwnerId(): Uuid { return $this->ownerId; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
}
