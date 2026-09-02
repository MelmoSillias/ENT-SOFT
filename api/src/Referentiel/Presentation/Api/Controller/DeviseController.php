<?php

namespace App\Referentiel\Presentation\Api\Controller;

use App\Referentiel\Domain\Entity\Devise;
use App\Referentiel\Domain\Enum\ModeArrondi;
use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api')]
final class DeviseController extends AbstractController
{
    public function __construct(
        private readonly DeviseRepositoryInterface $deviseRepository,
    ) {
    }

    #[Route('/devises', name: 'api_devises_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map([$this, 'serialize'], $this->deviseRepository->findAll()));
    }

    #[Route('/devises/{id}', name: 'api_devises_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        return $this->json($this->serialize($this->getDevise($id)));
    }

    #[Route('/devises', name: 'api_devises_create', methods: ['POST'])]
    #[IsGranted('referentiel.devises.manage')]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        if (empty($data['nom']) || empty($data['code']) || empty($data['mode_arrondi'])) {
            return $this->json(['error' => 'nom, code and mode_arrondi are required'], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $this->deviseRepository->findByCode((string) $data['code'])) {
            return $this->json(['error' => 'Code devise déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $devise = new Devise(
            nom: (string) $data['nom'],
            code: (string) $data['code'],
            modeArrondi: ModeArrondi::from((string) $data['mode_arrondi']),
            decimales: isset($data['decimales']) ? (int) $data['decimales'] : 2,
            symbole: $data['symbole'] ?? null,
            uniteArrondi: isset($data['unite_arrondi']) ? (string) $data['unite_arrondi'] : null,
        );

        if (array_key_exists('is_actif', $data)) {
            $devise->setIsActif((bool) $data['is_actif']);
        }

        $this->deviseRepository->save($devise);

        return $this->json($this->serialize($devise), Response::HTTP_CREATED);
    }

    #[Route('/devises/{id}', name: 'api_devises_update', methods: ['PUT'])]
    #[IsGranted('referentiel.devises.manage')]
    public function update(string $id, Request $request): JsonResponse
    {
        $devise = $this->getDevise($id);
        $data = $request->toArray();

        if (!empty($data['nom'])) {
            $devise->setNom((string) $data['nom']);
        }
        if (array_key_exists('symbole', $data)) {
            $devise->setSymbole($data['symbole'] !== '' ? (string) $data['symbole'] : null);
        }
        if (array_key_exists('decimales', $data)) {
            $devise->setDecimales((int) $data['decimales']);
        }
        if (!empty($data['mode_arrondi'])) {
            $devise->setModeArrondi(ModeArrondi::from((string) $data['mode_arrondi']));
        }
        if (array_key_exists('unite_arrondi', $data)) {
            $devise->setUniteArrondi($data['unite_arrondi'] !== '' && null !== $data['unite_arrondi'] ? (string) $data['unite_arrondi'] : null);
        }
        if (array_key_exists('is_actif', $data)) {
            $devise->setIsActif((bool) $data['is_actif']);
        }

        $this->deviseRepository->save($devise);

        return $this->json($this->serialize($devise));
    }

    private function getDevise(string $id): Devise
    {
        $devise = $this->deviseRepository->findById(Uuid::fromString($id));
        if (null === $devise) {
            throw $this->createNotFoundException('Devise introuvable.');
        }

        return $devise;
    }

    /** @return array<string, mixed> */
    private function serialize(Devise $devise): array
    {
        return [
            'id' => (string) $devise->getId(),
            'nom' => $devise->getNom(),
            'code' => $devise->getCode(),
            'symbole' => $devise->getSymbole(),
            'decimales' => $devise->getDecimales(),
            'mode_arrondi' => $devise->getModeArrondi()->value,
            'unite_arrondi' => $devise->getUniteArrondi(),
            'is_actif' => $devise->isActif(),
            'created_at' => $devise->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $devise->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
