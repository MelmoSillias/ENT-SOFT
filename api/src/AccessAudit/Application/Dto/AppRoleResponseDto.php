<?php

namespace App\AccessAudit\Application\Dto;

use App\AccessAudit\Domain\Entity\AppRole;

final readonly class AppRoleResponseDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $libelle,
        public bool $isSystem,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
        /** @var list<string> */
        public array $permissions = [],
    ) {
    }

    /** @param list<string> $permissions */
    public static function fromEntity(AppRole $role, array $permissions = []): self
    {
        return new self(
            id: (string) $role->getId(),
            code: $role->getCode(),
            libelle: $role->getLibelle(),
            isSystem: $role->isSystem(),
            isEnabled: $role->isEnabled(),
            createdAt: $role->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $role->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            permissions: $permissions,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'isSystem' => $this->isSystem,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'permissions' => $this->permissions,
        ];
    }
}
