<?php

namespace App\DataImport\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'data_import_records')]
class ImportRecord
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $id;

    #[ORM\Column(length: 64)]
    private string $fingerprint;

    #[ORM\Column(length: 36)]
    private string $entityId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $id, string $fingerprint, string $entityId)
    {
        $this->id = $id;
        $this->fingerprint = $fingerprint;
        $this->entityId = $entityId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getFingerprint(): string { return $this->fingerprint; }
    public function getEntityId(): string { return $this->entityId; }
}
