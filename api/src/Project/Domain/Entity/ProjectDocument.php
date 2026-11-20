<?php

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\ProjectDocumentType;
use App\Project\Infrastructure\Persistence\Doctrine\DoctrineProjectDocumentRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProjectDocumentRepository::class)]
#[ORM\Table(name: 'project_documents')]
#[ORM\Index(name: 'idx_project_document_project', columns: ['project_id'])]
class ProjectDocument
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $projectId;

    #[ORM\Column(length: 50, enumType: ProjectDocumentType::class)]
    private ProjectDocumentType $type;

    #[ORM\Column(length: 255)]
    private string $number;

        #[ORM\Column(length: 500, nullable: true)]
        private ?string $object = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'uuid')]
    private Uuid $clientId;

    /** @var list<array{description: string, quantity: float, observation: string}> */
    #[ORM\Column(type: 'json')]
    private array $lines;

    public function __construct(?Uuid $projectId, ProjectDocumentType $type, string $number, \DateTimeImmutable $date, Uuid $clientId, array $lines, ?string $object = null)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->projectId = $projectId;
        $this->replace($type, $number, $date, $clientId, $lines, $object);
    }

    public function replace(ProjectDocumentType $type, string $number, \DateTimeImmutable $date, Uuid $clientId, array $lines, ?string $object = null): void
    {
        $object = $object === null ? null : trim($object);
                if ($object !== null && mb_strlen($object) > 500) {
                    throw new \InvalidArgumentException('L’objet ne doit pas dépasser 500 caractères.');
                }
                $number = trim($number);
        if ($number === '' || mb_strlen($number) > 255) {
            throw new \InvalidArgumentException('Le numéro manuel est requis (255 caractères maximum).');
        }
        if (!array_is_list($lines) || $lines === []) {
            throw new \InvalidArgumentException('Au moins une ligne est requise.');
        }
        $normalized = [];
        foreach ($lines as $index => $line) {
            if (!is_array($line) || !is_string($line['description'] ?? null) || trim($line['description']) === '' || mb_strlen($line['description']) > 2000) {
                throw new \InvalidArgumentException(sprintf('Ligne %d : description requise (2000 caractères maximum).', $index + 1));
            }
            $quantity = $line['quantity'] ?? null;
            if ((!is_int($quantity) && !is_float($quantity)) || !is_finite((float) $quantity) || $quantity <= 0) {
                throw new \InvalidArgumentException(sprintf('Ligne %d : quantité numérique strictement positive requise.', $index + 1));
            }
            $observation = $line['observation'] ?? 'RAS';
            if (!is_string($observation) || mb_strlen($observation) > 2000) {
                throw new \InvalidArgumentException(sprintf('Ligne %d : observation invalide (2000 caractères maximum).', $index + 1));
            }
            $normalized[] = ['description' => trim($line['description']), 'quantity' => (float) $quantity, 'observation' => trim($observation) ?: 'RAS'];
        }
        $this->type = $type;
        $this->number = $number;
                $this->object = $object === '' ? null : $object;
        $this->date = $date;
        $this->clientId = $clientId;
        $this->lines = $normalized;
        $this->touch();
    }

    public function getProjectId(): ?Uuid { return $this->projectId; }
    public function assignProject(?Uuid $projectId): void
    {
        $this->projectId = $projectId;
        $this->touch();
    }
    public function getType(): ProjectDocumentType { return $this->type; }
    public function getNumber(): string { return $this->number; }
        public function getObject(): ?string { return $this->object; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getClientId(): Uuid { return $this->clientId; }
    public function getLines(): array { return $this->lines; }
}
