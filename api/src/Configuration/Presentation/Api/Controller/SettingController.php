<?php

namespace App\Configuration\Presentation\Api\Controller;

use App\Configuration\Application\Service\AgenceLogoUploadService;
use App\Configuration\Domain\Entity\HistoriqueSetting;
use App\Configuration\Domain\Entity\Setting;
use App\Configuration\Domain\Repository\HistoriqueSettingRepositoryInterface;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\SharedKernel\Application\Event\DomainEventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
final class SettingController extends AbstractController
{
    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
        private readonly HistoriqueSettingRepositoryInterface $historiqueSettingRepository,
        private readonly DomainEventDispatcherInterface $eventDispatcher,
        private readonly AgenceLogoUploadService $agenceLogoUploadService,
    ) {
    }

    #[Route('/settings', name: 'api_settings_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(): JsonResponse
    {
        return $this->json(array_map([$this, 'serialize'], $this->settingRepository->findAll()));
    }

    #[Route('/settings/agence-logo', name: 'api_settings_agence_logo_upload', methods: ['POST'])]
    #[IsGranted('configuration.settings.update')]
    public function uploadAgenceLogo(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Fichier image requis (champ file).'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $setting = $this->agenceLogoUploadService->upload($file, $currentUser);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($setting));
    }

    #[Route('/settings/agence-logo', name: 'api_settings_agence_logo_delete', methods: ['DELETE'])]
    #[IsGranted('configuration.settings.update')]
    public function deleteAgenceLogo(): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $setting = $this->agenceLogoUploadService->clear($currentUser);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($setting));
    }

    #[Route('/settings/{cle}', name: 'api_settings_show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(string $cle): JsonResponse
    {
        return $this->json($this->serialize($this->getSetting($cle)));
    }

    #[Route('/settings/{cle}', name: 'api_settings_update', methods: ['PUT'])]
    #[IsGranted('configuration.settings.update')]
    public function update(string $cle, Request $request): JsonResponse
    {
        $setting = $this->getSetting($cle);
        $data = $request->toArray();

        if (!isset($data['valeur'])) {
            return $this->json(['error' => 'valeur is required'], Response::HTTP_BAD_REQUEST);
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $ancienneValeur = $setting->getValeur();
        $nouvelleValeur = (string) $data['valeur'];
        $motif = isset($data['motif']) ? (string) $data['motif'] : null;

        $setting->modifier($nouvelleValeur, $currentUser->getId(), $motif);

        if (array_key_exists('description', $data)) {
            $setting->setDescription($data['description'] !== '' ? (string) $data['description'] : null);
        }

        $this->historiqueSettingRepository->save(new HistoriqueSetting(
            $setting->getCle(),
            $ancienneValeur,
            $nouvelleValeur,
            $currentUser->getId(),
            $motif,
        ), false);

        $this->settingRepository->save($setting, false);

        foreach ($setting->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        $this->settingRepository->save($setting);

        return $this->json($this->serialize($setting));
    }

    #[Route('/settings/{cle}/historique', name: 'api_settings_historique', methods: ['GET'])]
    #[IsGranted('configuration.settings.update')]
    public function historique(string $cle): JsonResponse
    {
        $this->getSetting($cle);

        $entries = $this->historiqueSettingRepository->findBySettingCle($cle);

        return $this->json(array_map(static fn (HistoriqueSetting $entry) => [
            'id' => (string) $entry->getId(),
            'setting_cle' => $entry->getSettingCle(),
            'ancienne_valeur' => $entry->getAncienneValeur(),
            'nouvelle_valeur' => $entry->getNouvelleValeur(),
            'utilisateur_id' => (string) $entry->getUtilisateurId(),
            'date_modification' => $entry->getDateModification()->format(\DateTimeInterface::ATOM),
            'motif' => $entry->getMotif(),
        ], $entries));
    }

    private function getSetting(string $cle): Setting
    {
        $setting = $this->settingRepository->findByCle($cle);
        if (null === $setting) {
            throw $this->createNotFoundException('Setting introuvable.');
        }

        return $setting;
    }

    /** @return array<string, mixed> */
    private function serialize(Setting $setting): array
    {
        return [
            'cle' => $setting->getCle(),
            'valeur' => $setting->getValeur(),
            'type' => $setting->getType()->value,
            'description' => $setting->getDescription(),
            'updated_at' => $setting->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
