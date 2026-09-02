<?php

namespace App\Project\Domain\Entity;

use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectEventRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectEventRepository::class)]
#[ORM\Table(name: 'project_events')]
class ProjectEvent
{
    use UuidEntityTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $projectId;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 255)]
    private string $title;

    public function __construct(Uuid $projectId, \DateTimeImmutable $date, string $title)
    {
        $this->initializeUuid();
        $this->projectId = $projectId;
        $this->date = $date;
        $this->title = $title;
    }

    public function getProjectId(): Uuid { return $this->projectId; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getTitle(): string { return $this->title; }
}
