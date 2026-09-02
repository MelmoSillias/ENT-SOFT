<?php

namespace App\Referentiel\Presentation\Api\Controller;

use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\Referentiel\Application\Command\CreatePaysDeviseLiaison\CreatePaysDeviseLiaisonCommand;
use App\Referentiel\Application\Command\CreatePaysDeviseLiaison\CreatePaysDeviseLiaisonHandler;
use App\Referentiel\Application\Command\UpdatePaysDeviseLiaison\UpdatePaysDeviseLiaisonCommand;
use App\Referentiel\Application\Command\UpdatePaysDeviseLiaison\UpdatePaysDeviseLiaisonHandler;
use App\Referentiel\Application\Query\GetHistoriqueTaux\GetHistoriqueTauxHandler;
use App\Referentiel\Domain\Entity\HistoriqueTaux;
use App\Referentiel\Domain\Entity\PaysDeviseLiaison;
use App\Referentiel\Domain\Exception\PaysDeviseLiaisonConflictException;
use App\Referentiel\Domain\Exception\PaysDeviseLiaisonNotFoundException;
use App\Referentiel\Domain\Repository\PaysDeviseLiaisonRepositoryInterface;
use App\SharedKernel\Domain\Exception\DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/pays-devise-liaisons')]
final class PaysDeviseLiaisonController extends AbstractController
{
    public function __construct(
        private readonly PaysDeviseLiaisonRepositoryInterface $liaisonRepository,
        private readonly CreatePaysDeviseLiaisonHandler $createHandler,
        private readonly UpdatePaysDeviseLiaisonHandler $updateHandler,
        private readonly GetHistoriqueTauxHandler $getHistoriqueTauxHandler,
    ) {
    }

    #[Route('', name: 'api_pays_devise_liaisons_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map([$this, 'serialize'], $this->liaisonRepository->findAll()));
    }

    #[Route('/historique', name: 'api_pays_devise_liaisons_historique', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function historique(): JsonResponse
    {
        if (!$this->canViewReferentiel()) {
            throw $this->createAccessDeniedException();
        }

        $entries = $this->getHistoriqueTauxHandler->handle();

        return $this->json(array_map([$this, 'serializeHistorique'], $entries));
    }

    #[Route('', name: 'api_pays_devise_liaisons_create', methods: ['POST'])]
    #[IsGranted('referentiel.pays.manage')]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        if (empty($data['pays_id']) || empty($data['devise_id']) || !isset($data['taux_defaut'])) {
            return $this->json(['error' => 'pays_id, devise_id and taux_defaut are required'], Response::HTTP_BAD_REQUEST);
        }

        $currentUser = $this->requireUtilisateur();
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        try {
            $liaison = ($this->createHandler)(new CreatePaysDeviseLiaisonCommand(
                paysId: Uuid::fromString((string) $data['pays_id']),
                deviseId: Uuid::fromString((string) $data['devise_id']),
                tauxDefaut: (string) $data['taux_defaut'],
                utilisateurId: $currentUser->getId(),
                isDefaut: (bool) ($data['is_defaut'] ?? false),
                motif: isset($data['motif']) ? (string) $data['motif'] : null,
            ));
        } catch (PaysDeviseLiaisonConflictException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serialize($liaison), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_pays_devise_liaisons_update', methods: ['PUT'])]
    #[IsGranted('referentiel.pays.manage')]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = $request->toArray();
        $currentUser = $this->requireUtilisateur();
        if ($currentUser instanceof JsonResponse) {
            return $currentUser;
        }

        try {
            $liaison = ($this->updateHandler)(new UpdatePaysDeviseLiaisonCommand(
                liaisonId: Uuid::fromString($id),
                utilisateurId: $currentUser->getId(),
                paysId: !empty($data['pays_id']) ? Uuid::fromString((string) $data['pays_id']) : null,
                deviseId: !empty($data['devise_id']) ? Uuid::fromString((string) $data['devise_id']) : null,
                tauxDefaut: array_key_exists('taux_defaut', $data) ? (string) $data['taux_defaut'] : null,
                isDefaut: array_key_exists('is_defaut', $data) ? (bool) $data['is_defaut'] : null,
                motif: isset($data['motif']) ? (string) $data['motif'] : null,
            ));
        } catch (PaysDeviseLiaisonNotFoundException) {
            throw $this->createNotFoundException('Liaison introuvable.');
        } catch (PaysDeviseLiaisonConflictException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serialize($liaison));
    }

    #[Route('/{id}', name: 'api_pays_devise_liaisons_delete', methods: ['DELETE'])]
    #[IsGranted('referentiel.pays.manage')]
    public function delete(string $id): JsonResponse
    {
        $liaison = $this->liaisonRepository->findById(Uuid::fromString($id));
        if (null === $liaison) {
            throw $this->createNotFoundException('Liaison introuvable.');
        }

        $this->liaisonRepository->remove($liaison);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function requireUtilisateur(): Utilisateur|JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $currentUser;
    }

    private function canViewReferentiel(): bool
    {
        return $this->isGranted('referentiel.pays.view')
            || $this->isGranted('referentiel.pays.manage')
            || $this->isGranted('referentiel.devises.view')
            || $this->isGranted('referentiel.devises.manage');
    }

    /** @return array<string, mixed> */
    private function serialize(PaysDeviseLiaison $liaison): array
    {
        $pays = $liaison->getPays();
        $devise = $liaison->getDevise();

        return [
            'id' => (string) $liaison->getId(),
            'pays_id' => (string) $pays->getId(),
            'pays_code' => $pays->getCode(),
            'pays_nom' => $pays->getNom(),
            'devise_id' => (string) $devise->getId(),
            'devise_code' => $devise->getCode(),
            'devise_symbole' => $devise->getSymbole(),
            'taux_defaut' => $liaison->getTauxDefaut(),
            'is_defaut' => $liaison->isDefaut(),
            'created_at' => $liaison->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $liaison->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeHistorique(HistoriqueTaux $entry): array
    {
        return [
            'id' => (string) $entry->getId(),
            'liaison_id' => (string) $entry->getLiaisonId(),
            'pays_code' => $entry->getPaysCode(),
            'pays_nom' => $entry->getPaysNom(),
            'devise_code' => $entry->getDeviseCode(),
            'devise_symbole' => $entry->getDeviseSymbole(),
            'ancien_taux' => $entry->getAncienTaux(),
            'nouveau_taux' => $entry->getNouveauTaux(),
            'utilisateur_id' => (string) $entry->getUtilisateurId(),
            'date_modification' => $entry->getDateModification()->format(\DateTimeInterface::ATOM),
            'motif' => $entry->getMotif(),
        ];
    }
}
