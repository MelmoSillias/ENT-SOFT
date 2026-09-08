<?php

namespace App\Upload\Domain\Entity;

use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Upload\Infrastructure\Persistence\Doctrine\DoctrineUploadSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineUploadSessionRepository::class)]
#[ORM\Table(name: 'upload_sessions')]
#[ORM\UniqueConstraint(name: 'uniq_upload_session_public_id', columns: ['public_id'])]
class UploadSession
{
    use UuidEntityTrait;
    use TimestampableTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_READY = 'ready';
    public const STATUS_ATTACHED = 'attached';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ABORTED = 'aborted';

    #[ORM\Column(length: 32)]
    private string $publicId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $ownerId;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private int $byteSize = 0;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $checksum = null;

    #[ORM\Column]
    private int $chunkSize = 4194304;

    #[ORM\Column]
    private int $chunkCount = 1;

    /** @var list<int> */
    #[ORM\Column(type: Types::JSON)]
    private array $receivedChunks = [];

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $assembledPath = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $attachedPath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        string $publicId,
        Uuid $ownerId,
        string $originalFilename,
        ?string $mimeType,
        int $byteSize,
        ?string $checksum,
        int $chunkSize,
        int $chunkCount,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->publicId = $publicId;
        $this->ownerId = $ownerId;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->byteSize = $byteSize;
        $this->checksum = $checksum;
        $this->chunkSize = $chunkSize;
        $this->chunkCount = $chunkCount;
        $this->expiresAt = $expiresAt;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getOwnerId(): Uuid
    {
        return $this->ownerId;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getByteSize(): int
    {
        return $this->byteSize;
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getChunkCount(): int
    {
        return $this->chunkCount;
    }

    /** @return list<int> */
    public function getReceivedChunks(): array
    {
        return array_values(array_map('intval', $this->receivedChunks));
    }

    public function markChunkReceived(int $index): void
    {
        $chunks = $this->getReceivedChunks();
        if (!in_array($index, $chunks, true)) {
            $chunks[] = $index;
            sort($chunks);
            $this->receivedChunks = $chunks;
            $this->touch();
        }
    }

    public function receivedBytes(): int
    {
        $count = count($this->getReceivedChunks());
        if ($count === 0) {
            return 0;
        }
        if ($count >= $this->chunkCount) {
            return $this->byteSize;
        }

        return min($this->byteSize, $count * $this->chunkSize);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isAttached(): bool
    {
        return $this->status === self::STATUS_ATTACHED;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UPLOADING], true);
    }

    public function getAssembledPath(): ?string
    {
        return $this->assembledPath;
    }

    public function setAssembledPath(?string $assembledPath): void
    {
        $this->assembledPath = $assembledPath;
        $this->touch();
    }

    public function getAttachedPath(): ?string
    {
        return $this->attachedPath;
    }

    public function setAttachedPath(?string $attachedPath): void
    {
        $this->attachedPath = $attachedPath;
        $this->touch();
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
