<?php

namespace App\Site\Domain\Entity;

use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Site\Infrastructure\Persistence\Doctrine\DoctrineSiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineSiteRepository::class)]
#[ORM\Table(name: 'sites')]
#[ORM\UniqueConstraint(name: 'uniq_site_code', fields: ['code'])]
class Site
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $clientId;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    public function __construct(
        string $code,
        string $title,
        ?string $description = null,
        ?Uuid $clientId = null,
        ?float $latitude = null,
        ?float $longitude = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->title = $title;
        $this->description = $description;
        $this->clientId = $clientId;
        $this->setLocation($latitude, $longitude);
    }

    public function getCode(): string { return $this->code; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getClientId(): ?Uuid { return $this->clientId; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function getLongitude(): ?float { return $this->longitude; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
    public function setClientId(?Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }

    public function setLocation(?float $latitude, ?float $longitude): void
    {
        if (null === $latitude && null === $longitude) {
            $this->latitude = null;
            $this->longitude = null;
            $this->touch();

            return;
        }

        if (null === $latitude || null === $longitude) {
            throw new \InvalidArgumentException('Latitude et longitude doivent être fournies ensemble.');
        }

        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new \InvalidArgumentException('La latitude doit être comprise entre -90 et 90.');
        }
        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new \InvalidArgumentException('La longitude doit être comprise entre -180 et 180.');
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->touch();
    }

    public function hasLocation(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }
}
