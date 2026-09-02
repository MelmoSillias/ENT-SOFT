<?php

namespace App\Referentiel\Presentation\Api\Controller;

use App\Referentiel\Domain\Entity\Pays;
use App\Referentiel\Domain\Entity\PaysDeviseLiaison;
use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysDeviseLiaisonRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api')]
final class PaysController extends AbstractController
{
    public function __construct(
        private readonly PaysRepositoryInterface $paysRepository,
        private readonly DeviseRepositoryInterface $deviseRepository,
        private readonly PaysDeviseLiaisonRepositoryInterface $liaisonRepository,
    ) {
    }

    #[Route('/pays', name: 'api_pays_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map([$this, 'serialize'], $this->paysRepository->findAll()));
    }

    #[Route('/pays/{id}', name: 'api_pays_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        return $this->json($this->serialize($this->getPays($id)));
    }

    #[Route('/pays', name: 'api_pays_create', methods: ['POST'])]
    #[IsGranted('referentiel.pays.manage')]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        if (empty($data['nom']) || empty($data['code']) || empty($data['devise_id'])) {
            return $this->json(['error' => 'nom, code and devise_id are required'], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $this->paysRepository->findByCode((string) $data['code'])) {
            return $this->json(['error' => 'Code pays déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $devise = $this->deviseRepository->findById(Uuid::fromString((string) $data['devise_id']));
        if (null === $devise) {
            return $this->json(['error' => 'Devise introuvable'], Response::HTTP_BAD_REQUEST);
        }

        $pays = new Pays(
            nom: (string) $data['nom'],
            code: (string) $data['code'],
            devise: $devise,
            indicatifTelephonique: $data['indicatif_telephonique'] ?? null,
        );

        if (array_key_exists('is_actif', $data)) {
            $pays->setIsActif((bool) $data['is_actif']);
        }

        $this->paysRepository->save($pays);

        return $this->json($this->serialize($pays), Response::HTTP_CREATED);
    }

    #[Route('/pays/{id}', name: 'api_pays_update', methods: ['PUT'])]
    #[IsGranted('referentiel.pays.manage')]
    public function update(string $id, Request $request): JsonResponse
    {
        $pays = $this->getPays($id);
        $data = $request->toArray();

        if (!empty($data['nom'])) {
            $pays->setNom((string) $data['nom']);
        }
        if (array_key_exists('indicatif_telephonique', $data)) {
            $pays->setIndicatifTelephonique($data['indicatif_telephonique'] !== '' ? (string) $data['indicatif_telephonique'] : null);
        }
        if (!empty($data['devise_id'])) {
            $devise = $this->deviseRepository->findById(Uuid::fromString((string) $data['devise_id']));
            if (null === $devise) {
                return $this->json(['error' => 'Devise introuvable'], Response::HTTP_BAD_REQUEST);
            }
            $pays->setDevise($devise);
        }
        if (array_key_exists('is_actif', $data)) {
            $pays->setIsActif((bool) $data['is_actif']);
        }

        $this->paysRepository->save($pays);

        return $this->json($this->serialize($pays));
    }

    private function getPays(string $id): Pays
    {
        $pays = $this->paysRepository->findById(Uuid::fromString($id));
        if (null === $pays) {
            throw $this->createNotFoundException('Pays introuvable.');
        }

        return $pays;
    }

    /** @return array<string, mixed> */
    private function serialize(Pays $pays): array
    {
        $devise = $pays->getDevise();

        $liaisons = $this->liaisonRepository->findByPaysId($pays->getId());

        return [
            'id' => (string) $pays->getId(),
            'nom' => $pays->getNom(),
            'code' => $pays->getCode(),
            'indicatif_telephonique' => $pays->getIndicatifTelephonique(),
            'devise_id' => (string) $pays->getDeviseId(),
            'devise_code' => $devise->getCode(),
            'devise_symbole' => $devise->getSymbole(),
            'devises_supportees' => array_map(
                static fn (PaysDeviseLiaison $l) => [
                    'id' => (string) $l->getId(),
                    'devise_id' => (string) $l->getDevise()->getId(),
                    'devise_code' => $l->getDevise()->getCode(),
                    'devise_symbole' => $l->getDevise()->getSymbole(),
                    'taux_defaut' => $l->getTauxDefaut(),
                    'is_defaut' => $l->isDefaut(),
                ],
                $liaisons,
            ),
            'is_actif' => $pays->isActif(),
            'created_at' => $pays->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $pays->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
