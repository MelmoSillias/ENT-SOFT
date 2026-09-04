<?php

namespace App\Employee\Application\Command\CreateEmployee;

use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use App\Employee\Application\Dto\EmployeeResponseDto;
use App\Employee\Domain\Entity\Employee;
use App\Employee\Domain\Repository\EmployeeRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateEmployeeHandler
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly UtilisateurRepositoryInterface $utilisateurRepository,
        private readonly AppRoleRepositoryInterface $appRoleRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(CreateEmployeeCommand $command): EmployeeResponseDto
    {
        $prenom = FieldValidator::requireNonEmpty($command->prenom, 'Prénom');
        $nom = FieldValidator::requireNonEmpty($command->nom, 'Nom');
        $email = FieldValidator::requireNonEmpty($command->email, 'Email');
        $phone = FieldValidator::requirePhone($command->phone);
        $roleCode = strtoupper(FieldValidator::requireNonEmpty($command->roleCode, 'Fonction'));

        $role = $this->appRoleRepository->findByCode($roleCode);
        if (null === $role || !$role->isEnabled()) {
            throw new \InvalidArgumentException('Fonction / rôle invalide ou masqué.');
        }

        $employee = new Employee(
            prenom: $prenom,
            nom: $nom,
            email: $email,
            phone: $phone,
            roleCode: $role->getCode(),
            address: $command->address,
        );

        $login = $this->generateUniqueLogin($prenom, $nom, $phone);
        $user = new Utilisateur(
            prenom: $prenom,
            nom: $nom,
            telephone: $phone,
            login: $login,
            passwordHash: '',
            roleCode: $role->getCode(),
        );
        $user->setIsActive(false);
        $tempPassword = bin2hex(random_bytes(8));
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $tempPassword));
        $this->utilisateurRepository->save($user);

        $employee->setUserId($user->getId());
        $this->employeeRepository->save($employee);

        return EmployeeResponseDto::fromEntity($employee);
    }

    private function generateUniqueLogin(string $prenom, string $nom, string $phone): string
    {
        $base = $this->slug($prenom !== '' ? $prenom : $nom);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $suffix = substr($digits, 0, 4);
        if ('' === $suffix) {
            $suffix = '0000';
        }

        $candidate = strtolower($base.$suffix);
        $i = 0;
        while (null !== $this->utilisateurRepository->findByLogin($candidate)) {
            ++$i;
            $candidate = strtolower($base.$suffix.$i);
        }

        return $candidate;
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower(preg_replace('/[^a-z0-9]+/i', '', $value) ?? '');

        return $value !== '' ? $value : 'user';
    }
}
