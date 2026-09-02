<?php

namespace App\IdentityAccess\Domain\Entity;

use App\IdentityAccess\Domain\Enum\Role;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\DoctrineUtilisateurRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: DoctrineUtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateurs')]
#[ORM\UniqueConstraint(name: 'uniq_utilisateur_login', fields: ['login'])]
#[ORM\UniqueConstraint(name: 'uniq_utilisateur_telephone', fields: ['telephone'])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 20)]
    private string $telephone;

    #[ORM\Column(length: 100)]
    private string $login;

    #[ORM\Column]
    private string $passwordHash;

    #[ORM\Column(enumType: Role::class)]
    private Role $role;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(
        string $prenom,
        string $nom,
        string $telephone,
        string $login,
        string $passwordHash,
        Role $role,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->prenom = $prenom;
        $this->nom = $nom;
        $this->telephone = $telephone;
        $this->login = strtolower($login);
        $this->passwordHash = $passwordHash;
        $this->role = $role;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setPrenom(string $prenom): void
    {
        $this->prenom = $prenom;
        $this->touch();
    }

    public function setNom(string $nom): void
    {
        $this->nom = $nom;
        $this->touch();
    }

    public function setTelephone(string $telephone): void
    {
        $this->telephone = $telephone;
        $this->touch();
    }

    public function setLogin(string $login): void
    {
        $this->login = strtolower($login);
        $this->touch();
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
        $this->touch();
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
        $this->touch();
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
        $this->touch();
    }

    public function getUserIdentifier(): string
    {
        return $this->login;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_'.$this->role->value];
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }
}
