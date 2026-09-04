<?php

namespace App\Employee\Application\Dto;

use App\Employee\Domain\Entity\Employee;

final readonly class EmployeeResponseDto
{
    public function __construct(
        public string $id,
        public string $prenom,
        public string $nom,
        public string $name,
        public string $email,
        public string $phone,
        public ?string $address,
        public string $roleCode,
        public ?string $userId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Employee $employee): self
    {
        return new self(
            id: (string) $employee->getId(),
            prenom: $employee->getPrenom(),
            nom: $employee->getNom(),
            name: $employee->getFullName(),
            email: $employee->getEmail(),
            phone: $employee->getPhone(),
            address: $employee->getAddress(),
            roleCode: $employee->getRoleCode(),
            userId: $employee->getUserId()?->toRfc4122(),
            isEnabled: $employee->isEnabled(),
            createdAt: $employee->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $employee->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prenom' => $this->prenom,
            'nom' => $this->nom,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'roleCode' => $this->roleCode,
            'function' => $this->roleCode,
            'userId' => $this->userId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
