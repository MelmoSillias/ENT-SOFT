<?php

namespace App\Project\Domain\Entity;

use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectLotRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectLotRepository::class)]
#[ORM\Table(name: 'project_lots')]
#[ORM\UniqueConstraint(name: 'uniq_project_lot_code', fields: ['projectId', 'code'])]
class ProjectLot
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $projectId;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title;

    public function __construct(Uuid $projectId, string $code, ?string $title = null)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->projectId = $projectId;
        $this->code = $code;
        $this->title = $title;
    }

    public function getProjectId(): Uuid { return $this->projectId; }
    public function getCode(): string { return $this->code; }
    public function getTitle(): ?string { return $this->title; }

    public function setTitle(?string $title): void { $this->title = $title; $this->touch(); }
}
