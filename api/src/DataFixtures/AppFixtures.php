<?php

namespace App\DataFixtures;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\Referentiel\Application\Service\ReferentielBootstrapService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ReferentielBootstrapService $bootstrapService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->bootstrapService->bootstrap();

        $users = [
            ['Admin', 'ENT', '+237600000001', 'admin', 'ADMIN'],
            ['Paul', 'Coordinateur', '+237600000002', 'coordinateur', 'COORDINATEUR'],
            ['Jean', 'Agent', '+237600000003', 'agent', 'AGENT'],
        ];

        foreach ($users as [$prenom, $nom, $telephone, $login, $roleCode]) {
            $user = new Utilisateur($prenom, $nom, $telephone, $login, 'placeholder', $roleCode);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, '123'));
            $manager->persist($user);
        }

        $manager->flush();
    }
}
